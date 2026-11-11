<?php

namespace Modules\WritingStyle\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Services\ModelResolver;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\SandboxPipelineRunner;
use Modules\WritingStyle\Services\PromptPack;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;
use Throwable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Lệnh CLI chạy bộ đánh giá prompt (chấm mù + báo cáo kỹ thuật).
 * =====================================================================
 *
 * Chạy writingstyle:eval; --live gọi provider đã cấu hình, mặc định chỉ in phạm vi bộ thử.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - handle(): Chạy đánh giá theo fixtures, tổng hợp usage và lưu kết quả từng phiên bản.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Analyzer, runner, ModelResolver và options --live/--limit/--repeats.
 * - OUTPUT: Mã SUCCESS hoặc FAILURE; khi chạy live có blind.json/report.json trên disk local.
 *
 * BỐI CẢNH SỬ DỤNG: Chạy writingstyle:eval; --live gọi provider đã cấu hình, mặc định chỉ in phạm vi bộ thử.
 * =====================================================================
 */
class EvaluateWritingPrompt extends Command
{
    protected $signature = 'writingstyle:eval {--live : Gọi model đã cấu hình} {--limit=20 : Số cặp bài mẫu và brief} {--repeats=2 : Số lần chạy mỗi brief}';

    protected $description = 'Chạy bộ đánh giá prompt bằng dữ liệu thử nghiệm và tạo bản chấm mù';

    /**
     * =====================================================================
     * CHỨC NĂNG: Chạy đánh giá theo fixtures, tổng hợp usage và lưu kết quả từng phiên bản
     * =====================================================================
     *
     * INPUT:
     * - WritingStyleAnalyzerService $analyzer, SandboxPipelineRunner $runner, ModelResolver $models
     *
     * OUTPUT:
     * - int mã thoát của command
     *
     * SIDE EFFECT:
     * - Khi --live: gọi model, tạo analysis/AiRun/usage, tạm đổi config và khôi phục trong finally; ghi writing-style-evals trên disk local. Không lưu profile thử vào DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Bắt lỗi resolve và lỗi trong vòng đánh giá thành FAILURE. Lỗi truy vấn usage hoặc storage dạng exception có thể truyền lên. Không mở transaction hoặc yêu cầu HTTP auth.
     * =====================================================================
     */
    public function handle(WritingStyleAnalyzerService $analyzer, SandboxPipelineRunner $runner, ModelResolver $models): int
    {
        $groups = require module_path('WritingStyle', 'tests/Fixtures/prompt-eval.php');
        $limit = max(1, min(20, (int) $this->option('limit')));
        $repeats = max(1, min(5, (int) $this->option('repeats')));
        $this->info("{$limit} brief × {$repeats} lần. Mỗi nhóm giải mã một lần.");
        if (! $this->option('live')) {
            return self::SUCCESS;
        }

        try {
            foreach (['structured_output', 'text_generation'] as $capability) {
                $resolved = $models->resolve($capability);
                $this->line($capability.' '.$resolved->provider->key.'/'.$resolved->model->remote_model_id);
            }
        } catch (AiGatewayException $error) {
            $this->error('Chưa có model hoặc kết nối đủ điều kiện để đánh giá ('.$error->errorCode().').');
            return self::FAILURE;
        }

        $report = ['brief_count' => $limit, 'repeats' => $repeats, 'results' => [], 'errors' => []];
        $blind = [];
        $runs = [];
        try {
            $remaining = $limit;
            foreach ($groups as $group => $fixture) {
                if ($remaining === 0) {
                    break;
                }
                $analysis = $analyzer->analyze($fixture['source']);
                $result = $analysis->result_json ?? [];
                if (! $analysis->isSucceeded()) {
                    throw new \RuntimeException('Phân tích thất bại cho nhóm '.$group.' ('.$analysis->error_code.').');
                }
                $runs[] = $result['ai_run_id'] ?? null;
                $profile = new WritingProfile(array_intersect_key($result, array_flip([
                    'rules_json', 'style_instructions', 'voice_texture_instructions',
                    'voice_texture_sliders_json', 'tone_sliders_json', 'style_analysis_json',
                ])));
                $profile->name = 'Eval '.$group;
                $styleAnalysis = $profile->style_analysis_json ?? [];
                $styleAnalysis['source_specific_items'] = $fixture['source_terms'] ?? [];
                $profile->style_analysis_json = $styleAnalysis;

                foreach (array_slice($fixture['targets'], 0, $remaining) as $index => [$topic, $fact, $cta]) {
                    $remaining--;
                    $brief = [
                        'platform' => $group === 'technical' ? 'blog' : 'facebook',
                        'content_type' => $fixture['type'],
                        'objective' => $cta,
                        'provided_facts' => [['id' => 'f1', 'text' => $fact, 'source' => 'user']],
                        'required_details' => [$fact],
                        'cta' => $cta,
                        'length' => ['unit' => 'words', 'min' => 40, 'max' => 100],
                        'format' => ['headings' => $group === 'technical'],
                        'constraints' => ['Không thêm giá, địa chỉ hoặc ưu đãi ngoài dữ kiện.'],
                    ];
                    for ($repeat = 1; $repeat <= $repeats; $repeat++) {
                        $case = $group.'-'.($index + 1).'-'.$repeat;
                        $started = hrtime(true);
                        $artifacts = [];
                        $overrides = ['brief' => $brief, 'temperature' => 0.7, 'outline_approved' => true];
                        if ($fixture['tier'] === 'long') {
                            $artifacts['topic_outline'] = $runner->runStep($profile, 'long', 'topic_outline', $topic, [], $overrides)['artifact'];
                            $runs[] = $artifacts['topic_outline']['ai_run_id'] ?? null;
                        }
                        $article = $runner->runStep($profile, $fixture['tier'], 'article_writer', $topic, $artifacts, $overrides)['artifact'];
                        $runs[] = $article['ai_run_id'] ?? null;
                        $label = Str::random(12);
                        $blind[$case]['brief'] = ['topic' => $topic, ...$brief];
                        $blind[$case]['sample'] = $fixture['source'];
                        $blind[$case]['articles'][$label] = $article['text'];
                        $report['results'][] = [
                            'case' => $case, 'label' => $label,
                            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                            'provenance' => $article['snapshot']['provenance'],
                            'quality_report' => $article['quality_report'],
                        ];
                        $this->line($case);
                    }
                }
            }
        } catch (Throwable $error) {
            $report['errors'][] = $error instanceof AiGatewayException ? $error->errorCode() : $error::class;
            $this->error('Đợt đánh giá bị dừng. Báo cáo giữ các ca đã chạy.');
        }

        $runIds = array_values(array_unique(array_filter($runs)));
        $report['usage'] = DB::table('ai_usage_logs')->whereIn('ai_run_id', $runIds)
            ->get(['ai_run_id', 'provider', 'model', 'input_units', 'output_units', 'estimated_cost_usd'])->all();
        $report['logged_ai_calls'] = count($runIds);
        $report['manual_review_pending'] = true;
        foreach ($blind as &$pair) {
            ksort($pair['articles']);
        }
        unset($pair);
        $directory = 'writing-style-evals/'.now()->format('Ymd-His').'-'.Str::random(6);
        if (! Storage::disk('local')->put($directory.'/blind.json', PromptPack::json($blind))
            || ! Storage::disk('local')->put($directory.'/report.json', PromptPack::json($report))) {
            $this->error('Không thể lưu báo cáo đánh giá.');
            return self::FAILURE;
        }
        $this->info('Đã lưu kết quả đánh giá tại '.$directory);

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
