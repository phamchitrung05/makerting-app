<?php

namespace Modules\WritingStyle\Pipelines;

use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ điều phối thực thi Sandbox Pipeline (runtime).
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
 */
class SandboxPipelineRunner
{
    public function __construct(
        private readonly PipelineTopologyValidator $validator,
    ) {}

    /* =====================================================================
     * Truy xuất config + instance step (qua validator để nhất quán lỗi).
     * ===================================================================== */

    /** CHỨC NĂNG: Trả mảng pipeline thô của 1 tier từ config. */
    private function rawPipeline(string $tier): array
    {
        return (array) config('writingstyle.tier_pipelines.' . $tier, []);
    }

    /** CHỨC NĂNG: Resolve instance step theo key (qua validator -> lỗi chuẩn). */
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
     * CHỨC NĂNG: Trả sơ đồ pipeline đã chuẩn hóa của 4 tier.
     * =====================================================================
     * INPUT: không. OUTPUT:
     *   array{tier => array{steps => array<{step,label,description,icon,stage,is_terminal,depends_on}>}}
     * SIDE EFFECT: validateTierPipeline mọi tier (fail-fast khi config sai).
     * EXCEPTION: InvalidPipelineException nếu config bất kỳ tier nào vi phạm.
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
