<?php

namespace Modules\WritingStyle\Pipelines;

use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Điều phối thứ tự và phụ thuộc các bước sandbox.
 * =====================================================================
 *
 * Hai nhiệm vụ:
 *  1. `describePipelines()` — trả sơ đồ pipeline đã chuẩn hóa của cả 4 tier
 *     cho endpoint `GET /options` (FE render Pipeline Stepper biết trước
 *     các bước + trạng thái phụ thuộc mà không cần hardcode).
 *  2. `runStep()` — chạy 1 step đúng thứ tự + phụ thuộc:
 *     - `PipelineTopologyValidator::validateTierPipeline` (config-time).
 *     - `assertDependenciesMet` (runtime guard `artifacts`).
 *     - Thu `compileToPromptBlock()` của mọi step đứng trước step hiện tại,
 *       truyền vào `$step->execute()` dưới dạng `$upstreamPromptBlocks`.
 *
 * KHÔNG lưu session server — artifacts giữa các bước do FE giữ và gửi lại.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của SandboxPipelineRunner.
 * - rawPipeline(): Trả mảng pipeline thô của 1 tier từ config.
 * - step(): Resolve step đã đăng ký từ container và kiểm tra contract.
 * - describePipelines(): Validate và mô tả pipeline của cả bốn tier cho frontend.
 * - runStep(): Validate topology, phụ thuộc và dàn ý V3 rồi thực thi bước được yêu cầu.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Profile, tier, step, topic, artifacts do client giữ và overrides.
 * - OUTPUT: Mô tả pipeline hoặc cặp step/artifact.
 *
 * BỐI CẢNH SỬ DỤNG: Controller gọi runStep; endpoint options lấy sơ đồ qua describePipelines().
 * =====================================================================
 */
class SandboxPipelineRunner
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của SandboxPipelineRunner
     * =====================================================================
     *
     * INPUT:
     * - private readonly PipelineTopologyValidator $validator,
     *
     * OUTPUT:
     * - Instance giữ các thuộc tính/dependency theo chữ ký
     *
     * SIDE EFFECT:
     * - Gán thuộc tính trong bộ nhớ; không gọi AI hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi kiểu dữ liệu truyền về caller; không mở transaction.
     * =====================================================================
     */
    public function __construct(
        private readonly PipelineTopologyValidator $validator,
    ) {}

    /* =====================================================================
     * Truy xuất config + instance step (qua validator để nhất quán lỗi).
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Trả mảng pipeline thô của 1 tier từ config.
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - string $tier
     *
     * OUTPUT:
     * - array theo hợp đồng của method.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    private function rawPipeline(string $tier): array
    {
        return (array) config('writingstyle.tier_pipelines.' . $tier, []);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve step đã đăng ký từ container và kiểm tra contract
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - string $stepKey
     *
     * OUTPUT:
     * - SandboxStepContract đã resolve
     *
     * SIDE EFFECT:
     * - Đọc config và resolve dependency từ container.
     *
     * EXCEPTION/TRANSACTION:
     * - InvalidPipelineException khi thiếu/sai class hoặc contract; lỗi container truyền lên. Không mở transaction.
     * =====================================================================
     */
    private function step(string $stepKey): SandboxStepContract
    {
        $map = (array) config('writingstyle.sandbox_steps', []);
        $class = $map[$stepKey] ?? null;

        if ($class === null || ! is_string($class) || ! class_exists($class)) {
            throw new InvalidPipelineException(
                "Step '{$stepKey}' chưa được đăng ký trong config('writingstyle.sandbox_steps')."
            );
        }

        $instance = app($class);
        if (! $instance instanceof SandboxStepContract) {
            throw new InvalidPipelineException(
                "Class [{$class}] đăng ký cho step '{$stepKey}' không implements SandboxStepContract."
            );
        }

        return $instance;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Validate và mô tả pipeline của cả bốn tier cho frontend
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Trả sơ đồ pipeline đã chuẩn hóa của 4 tier.
     * =====================================================================
     * INPUT: không. OUTPUT:
     *   array{tier => array{steps => array<{step,label,description,icon,stage,is_terminal,depends_on}>}}
     * SIDE EFFECT: validateTierPipeline mọi tier (fail-fast khi config sai).
     * EXCEPTION: InvalidPipelineException nếu config bất kỳ tier nào vi phạm.
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array tier => steps cùng nhãn, stage, is_terminal và depends_on
     *
     * SIDE EFFECT:
     * - Validate cấu hình mỗi tier và resolve metadata step; không chạy AI.
     *
     * EXCEPTION/TRANSACTION:
     * - InvalidPipelineException khi topology/step sai; lỗi container truyền lên. Không mở transaction.
     * =====================================================================
     */
    public function describePipelines(): array
    {
        $out = [];
        foreach (['micro', 'short', 'medium', 'long'] as $tier) {
            $this->validator->validateTierPipeline($tier);

            $steps = [];
            foreach ($this->rawPipeline($tier) as $cfg) {
                $stepKey = (string) ($cfg['step'] ?? '');
                $step = $this->step($stepKey);

                $steps[] = array_merge(
                    [
                        'step'          => $stepKey,
                        'depends_on'    => array_values(array_unique(array_merge(
                            (array) ($cfg['depends_on'] ?? []),
                            $step->intrinsicDependsOn(),
                        ))),
                    ],
                    $step->meta(),
                );
            }

            $out[$tier] = ['steps' => $steps];
        }

        return $out;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Validate topology, phụ thuộc và dàn ý V3 rồi thực thi bước được yêu cầu
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Thực thi 1 step trong pipeline của 1 tier.
     * =====================================================================
     * INPUT :
     *   - WritingProfile $profile — hồ sơ văn phong đang dùng.
     *   - string $targetTier — tier đích (micro|short|medium|long).
     *   - string $stepKey — step cần chạy (phải nằm trong pipeline tier này).
     *   - string $topic — chủ đề user nhập.
     *   - array $artifacts — map stepKey => artifact của các step đã chạy trước
     *     (FE giữ giữa các lần gọi và gửi lại ở bước sau).
     *   - array $overrides — runtime (tone_overrides/temperature/style/voice...).
     *
     * OUTPUT: array — { step, artifact } — FE lưu artifact vào map để gửi bước sau.
     *
     * SIDE EFFECT: gọi AI qua step->execute (ghi AiRun + ai_usage_logs).
     * EXCEPTION: InvalidPipelineException (422) khi vi phạm phụ thuộc hoặc
     *            step không nằm trong pipeline của tier.
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $targetTier, string $stepKey, string $topic, array $artifacts = [], array $overrides = [],
     *
     * OUTPUT:
     * - array step và artifact
     *
     * SIDE EFFECT:
     * - V3 yêu cầu outline_approved, kiểm tra schema/fingerprint và validate lại kế hoạch. Ghép prompt block từ các bước trước; step có thể gọi AI.
     *
     * EXCEPTION/TRANSACTION:
     * - InvalidPipelineException khi sai bước/phụ thuộc, chưa duyệt hoặc dàn ý cũ; ValidationException và lỗi AI có thể truyền lên. Không mở transaction, không tự lưu session.
     * =====================================================================
     */
    public function runStep(
        WritingProfile $profile,
        string $targetTier,
        string $stepKey,
        string $topic,
        array $artifacts = [],
        array $overrides = [],
    ): array {
        // 1. Config-time: pipeline tier phải hợp lệ (thứ tự + terminal + deps).
        $this->validator->validateTierPipeline($targetTier);

        // 2. Step phải nằm trong pipeline của tier đang chọn.
        $inPipeline = false;
        foreach ($this->rawPipeline($targetTier) as $cfg) {
            if (($cfg['step'] ?? null) === $stepKey) {
                $inPipeline = true;
                break;
            }
        }
        if (! $inPipeline) {
            throw new InvalidPipelineException(
                "Step '{$stepKey}' không nằm trong pipeline của tier '{$targetTier}'."
            );
        }

        // 3. Runtime guard: mọi dependency của step phải đã có artifact hợp lệ.
        $this->validator->assertDependenciesMet($targetTier, $stepKey, $artifacts);

        if ($stepKey !== 'topic_outline' && isset($artifacts['topic_outline'])) {
            if (! ($overrides['outline_approved'] ?? false)) {
                throw new InvalidPipelineException('Hãy xác nhận dàn ý trước khi viết bài.');
            }
            $plan = $artifacts['topic_outline'];
            $brief = WritingBrief::fromInput($topic, $targetTier, $overrides);
            $snapshot = app(WritingStyleCompilerContract::class)->buildSnapshot(
                $profile,
                $targetTier,
                (int) ($overrides['target_sentences'] ?? 0),
                array_replace($overrides, ['brief' => $brief->data]),
            );
            if (($plan['schema_version'] ?? null) !== 3 || ($plan['input_fingerprint'] ?? null) !== WritingContentPlan::fingerprint($snapshot, $brief)) {
                throw new InvalidPipelineException('Dàn ý đã cũ hoặc khác phiên bản. Hãy lập lại dàn ý trước khi viết.');
            }
            $artifacts['topic_outline'] = [
                ...WritingContentPlan::validate($plan, $brief),
                'schema_version' => 3,
            ];
        }

        // 4. Gom PromptBlock của tất cả step đứng TRƯỚC step hiện tại trong
        //    pipeline (theo đúng thứ tự config), compileToPromptBlock đã sẵn.
        $upstreamPromptBlocks = [];
        foreach ($this->rawPipeline($targetTier) as $cfg) {
            $priorKey = (string) ($cfg['step'] ?? '');
            if ($priorKey === $stepKey) {
                break; // dừng tại step hiện tại — không lấy chính nó.
            }
            if (array_key_exists($priorKey, $artifacts) && is_array($artifacts[$priorKey])) {
                $priorStep = $this->step($priorKey);
                $upstreamPromptBlocks[] = [
                    'step'  => $priorKey,
                    'block' => $priorStep->compileToPromptBlock($artifacts[$priorKey]),
                ];
            }
        }

        $artifact = $this->step($stepKey)->execute(
            $profile,
            $topic,
            $targetTier,
            $upstreamPromptBlocks,
            $overrides,
        );

        return ['step' => $stepKey, 'artifact' => $artifact];
    }
}
