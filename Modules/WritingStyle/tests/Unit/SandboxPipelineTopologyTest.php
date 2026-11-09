<?php

namespace Modules\WritingStyle\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Pipelines\PipelineTopologyValidator;
use Modules\WritingStyle\Pipelines\SandboxPipelineRunner;
use Modules\WritingStyle\Pipelines\Steps\ArticleWriterStep;
use Modules\WritingStyle\Pipelines\Steps\TopicOutlineStep;
use Tests\TestCase;

/**
 * Test bộ kiểm định thứ tự & phụ thuộc của Sandbox Pipeline (Task 2/6).
 *
 * 5 kịch bản bắt buộc theo docs/writing-style-sandbox-pipeline.md §6:
 *   1. Config đúng thứ tự -> hợp lệ.
 *   2. Config ngược thứ tự (stage lớn trước nhỏ) -> throw.
 *   3. Config thiếu bước terminal ở cuối -> throw.
 *   4. depends_on trỏ tới step chưa khai báo phía trước -> throw.
 *   5. Runtime guard: chạy article_writer ở tier long khi thiếu artifacts.topic_outline -> 422.
 */
class SandboxPipelineTopologyTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): WritingProfile
    {
        $p = new WritingProfile;
        $p->id = 1;
        $p->name = 'Giọng Test';
        $p->dominant_tier = WritingProfile::TIER_LONG;
        $p->rules_json = ['tone' => 'ấm áp'];
        $p->evidence_json = [];

        return $p;
    }

    /** Config hợp lệ gốc (dùng để restore sau mỗi test chỉnh config). */
    private function snapshotConfig(): array
    {
        return (array) config('writingstyle.tier_pipelines', []);
    }

    private function restoreConfig(array $cfg): void
    {
        config(['writingstyle.tier_pipelines' => $cfg]);
    }

    // -----------------------------------------------------------------
    // 1. Config đúng -> hợp lệ.
    // -----------------------------------------------------------------
    public function test_pipeline_long_dung_thu_tu_hop_le(): void
    {
        $validator = app(PipelineTopologyValidator::class);
        // Không ném exception -> config mặc định của repo đúng.
        $validator->validateTierPipeline('long');
        $this->addToAssertionCount(1);
    }

    // -----------------------------------------------------------------
    // 2. Sai thứ tự stage -> throw.
    // -----------------------------------------------------------------
    public function test_pipeline_sai_thu_tu_stage_bi_chan(): void
    {
        $orig = $this->snapshotConfig();
        try {
            // Đặt step stage=20 (topic_outline) trước, rồi step stage=100 (article_writer)
            // đúng — nhưng đảo lại: đặt article_writer (100) TRƯỚC một step stage=50 giả.
            // Để test check stage, dùng một step giả stage=50 không terminal.
            // Tuy nhiên không có step nào stage 50 — nên test bằng cách đảo
            // article_writer(100) trước topic_outline(20): phải báo lỗi. Vì
            // article_writer là terminal nó cũng vi phạm terminal — kiểm tra
            // message nào bắt được (stage hoặc terminal đều được chấp nhận).
            config(['writingstyle.tier_pipelines' => [
                'long' => [
                    ['step' => 'article_writer', 'depends_on' => []],
                    ['step' => 'topic_outline',  'depends_on' => []],
                ],
            ]]);

            $this->expectException(InvalidPipelineException::class);
            // Chấp nhận message về thứ tự stage hoặc về terminal (cả 2 đều vi phạm).
            $this->expectExceptionMessageMatches('/(Sai thứ tự stage|kết thúc|terminal)/i');

            app(PipelineTopologyValidator::class)->validateTierPipeline('long');
        } finally {
            $this->restoreConfig($orig);
        }
    }

    // -----------------------------------------------------------------
    // 3. Thiếu bước terminal ở cuối -> throw.
    // -----------------------------------------------------------------
    public function test_pipeline_thieu_buoc_terminal_bi_chan(): void
    {
        $orig = $this->snapshotConfig();
        try {
            config(['writingstyle.tier_pipelines' => [
                'long' => [
                    ['step' => 'topic_outline', 'depends_on' => []],
                    // thiếu article_writer (is_terminal) ở cuối.
                ],
            ]]);

            $this->expectException(InvalidPipelineException::class);
            $this->expectExceptionMessageMatches('/kết thúc/');

            app(PipelineTopologyValidator::class)->validateTierPipeline('long');
        } finally {
            $this->restoreConfig($orig);
        }
    }

    // -----------------------------------------------------------------
    // 4. depends_on trỏ tới step chưa khai báo phía trước -> throw.
    // -----------------------------------------------------------------
    public function test_pipeline_depends_on_step_chua_co_bi_chan(): void
    {
        $orig = $this->snapshotConfig();
        try {
            config(['writingstyle.tier_pipelines' => [
                'long' => [
                    ['step' => 'article_writer', 'depends_on' => ['topic_outline']],
                    // topic_outline KHÔNG xuất hiện phía trước -> vi phạm.
                ],
            ]]);

            $this->expectException(InvalidPipelineException::class);
            $this->expectExceptionMessageMatches('/phụ thuộc/');

            app(PipelineTopologyValidator::class)->validateTierPipeline('long');
        } finally {
            $this->restoreConfig($orig);
        }
    }

    // -----------------------------------------------------------------
    // 5. Runtime guard: thiếu artifacts.topic_outline -> 422.
    // -----------------------------------------------------------------
    public function test_run_article_writer_thieu_artifact_bi_chan(): void
    {
        $runner = app(SandboxPipelineRunner::class);

        $this->expectException(InvalidPipelineException::class);
        $this->expectExceptionMessageMatches('/artifacts/');

        // Chạy article_writer ở tier long mà KHÔNG truyền artifacts.topic_outline.
        $runner->runStep(
            $this->profile(),
            'long',
            'article_writer',
            'chủ đề X',
            [], // artifacts rỗng -> thiếu topic_outline.
            [],
        );
    }

    // -----------------------------------------------------------------
    // Bonus: describePipelines trả đúng sơ đồ 4 tier.
    // -----------------------------------------------------------------
    public function test_describe_pipelines_tra_dung_so_do_4_tier(): void
    {
        $runner = app(SandboxPipelineRunner::class);
        $pipelines = $runner->describePipelines();

        $this->assertArrayHasKey('micro', $pipelines);
        $this->assertArrayHasKey('short', $pipelines);
        $this->assertArrayHasKey('medium', $pipelines);
        $this->assertArrayHasKey('long', $pipelines);

        // Long có 2 bước: topic_outline -> article_writer.
        $longSteps = collect($pipelines['long']['steps'] ?? [])->pluck('step')->all();
        $this->assertSame(['topic_outline', 'article_writer'], $longSteps);

        // Micro chỉ 1 bước: article_writer.
        $microSteps = collect($pipelines['micro']['steps'] ?? [])->pluck('step')->all();
        $this->assertSame(['article_writer'], $microSteps);

        // Step phải kèm meta (label/description/icon/stage/is_terminal).
        $outlineMeta = collect($pipelines['long']['steps'] ?? [])
            ->firstWhere('step', 'topic_outline');
        $this->assertNotNull($outlineMeta);
        $this->assertSame(20, $outlineMeta['stage']);
        $this->assertFalse($outlineMeta['is_terminal']);
    }
}
