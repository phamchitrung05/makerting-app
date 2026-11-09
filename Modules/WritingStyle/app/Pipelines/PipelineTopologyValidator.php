<?php

namespace Modules\WritingStyle\Pipelines;

use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm định cấu trúc Sandbox Pipeline (config-time).
 * =====================================================================
 *
 * Quét `config('writingstyle.tier_pipelines')` + `sandbox_steps` để chặn
 * 3 lớp vi phạm TRƯỚC khi runner thực thi:
 *   1. Step không tồn tại trong `sandbox_steps`.
 *   2. Sai thứ tự `stage` (stage lớn đặt trước stage nhỏ) hoặc bước
 *      `is_terminal` không ở cuối mảng.
 *   3. `depends_on` (config + `intrinsicDependsOn()`) trỏ tới step chưa
 *      khai báo phía trước trong cùng tier.
 *
 * Cache instance Step theo key (lazy resolve qua container).
 * =====================================================================
 */
class PipelineTopologyValidator
{
    /** @var array<string, SandboxStepContract> cache step đã resolve. */
    private array $stepCache = [];

    /**
     * CHỨC NĂNG: Resolve instance của 1 step theo key.
     * INPUT: string $stepKey. OUTPUT: SandboxStepContract.
     * SIDE EFFECT: ghi vào stepCache.
     * EXCEPTION: InvalidPipelineException nếu key chưa đăng ký trong sandbox_steps.
     */
    private function step(string $stepKey): SandboxStepContract
    {
        if (isset($this->stepCache[$stepKey])) {
            return $this->stepCache[$stepKey];
        }

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

        $this->stepCache[$stepKey] = $instance;

        return $instance;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra toàn bộ pipeline của 1 tier có hợp lệ không.
     * =====================================================================
     * INPUT: string $tier — 'micro'|'short'|'medium'|'long'.
     * OUTPUT: void — ném InvalidPipelineException khi vi phạm.
     * EXCEPTION: InvalidPipelineException (422) với message tường minh.
     * =====================================================================
     */
    public function validateTierPipeline(string $tier): void
    {
        $pipeline = (array) (config('writingstyle.tier_pipelines.' . $tier, []));
        if ($pipeline === []) {
            throw new InvalidPipelineException(
                "Tier '{$tier}' chưa cấu hình pipeline trong config('writingstyle.tier_pipelines')."
            );
        }

        $seenKeys = [];        // các step đã xuất hiện phía trước trong pipeline
        $lastStage = -1;       // stage lớn nhất đã thấy (để chặn thứ tự sai)
        $count = count($pipeline);

        foreach ($pipeline as $idx => $cfg) {
            $stepKey = (string) ($cfg['step'] ?? '');
            $configDeps = (array) ($cfg['depends_on'] ?? []);

            if ($stepKey === '') {
                throw new InvalidPipelineException(
                    "Pipeline tier '{$tier}' phần tử #{$idx} thiếu khóa 'step'."
                );
            }

            $step = $this->step($stepKey);
            $stage = $step->stage();
            $isTerminal = $step->isTerminal();
            $isLast = $idx === $count - 1;

            // 1. Sai thứ tự stage — stage phải tăng dần nghiêm ngặt.
            if ($stage <= $lastStage) {
                throw new InvalidPipelineException(
                    "Sai thứ tự stage trong tier '{$tier}': step '{$stepKey}' " .
                    "(stage={$stage}) đứng sau một step có stage={$lastStage}." .
                    " Stage phải tăng dần từ trên xuống dưới."
                );
            }
            $lastStage = $stage;

            // 2. Vi phạm terminal — bước is_terminal chỉ được ở cuối; phần
            //    tử cuối cùng BẮT BUỘC phải is_terminal = true.
            if ($isTerminal && ! $isLast) {
                throw new InvalidPipelineException(
                    "Step '{$stepKey}' là bước kết thúc (is_terminal=true) " .
                    "nhưng không đứng ở cuối pipeline của tier '{$tier}'."
                );
            }
            if ($isLast && ! $isTerminal) {
                throw new InvalidPipelineException(
                    "Phần tử cuối cùng của pipeline tier '{$tier}' phải là bước " .
                    "kết thúc (is_terminal=true), nhưng '{$stepKey}' không phải."
                );
            }

            // 3. depends_on (config + intrinsic) — mọi dependency phải đã
            //    xuất hiện ở vị trí đứng trước step này trong cùng tier.
            $deps = array_values(array_unique(array_merge($configDeps, $step->intrinsicDependsOn())));
            foreach ($deps as $depKey) {
                if (! in_array($depKey, $seenKeys, true)) {
                    throw new InvalidPipelineException(
                        "Step '{$stepKey}' trong tier '{$tier}' phụ thuộc vào " .
                        "'{$depKey}' nhưng step này chưa xuất hiện ở vị trí đứng " .
                        "trước trong pipeline."
                    );
                }
            }

            $seenKeys[] = $stepKey;
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra artifacts runtime có đủ dependency cho 1 step.
     * =====================================================================
     * INPUT :
     *   - string $tier — tier đang chạy.
     *   - string $stepKey — step sắp execute.
     *   - array $artifacts — map stepKey => artifact đã có.
     * OUTPUT: void — ném InvalidPipelineException nếu thiếu/không hợp lệ.
     * SIDE EFFECT: gọi validateTierPipeline trước (đảm bảo config vẫn đúng).
     * =====================================================================
     */
    public function assertDependenciesMet(string $tier, string $stepKey, array $artifacts): void
    {
        $this->validateTierPipeline($tier);

        $pipeline = (array) config('writingstyle.tier_pipelines.' . $tier, []);
        $cfg = null;
        foreach ($pipeline as $el) {
            if (($el['step'] ?? null) === $stepKey) {
                $cfg = $el;
                break;
            }
        }
        if ($cfg === null) {
            throw new InvalidPipelineException(
                "Step '{$stepKey}' không nằm trong pipeline của tier '{$tier}'."
            );
        }

        $step = $this->step($stepKey);
        $deps = array_values(array_unique(array_merge(
            (array) ($cfg['depends_on'] ?? []),
            $step->intrinsicDependsOn()
        )));

        foreach ($deps as $depKey) {
            if (! array_key_exists($depKey, $artifacts)) {
                throw new InvalidPipelineException(
                    "Bước '{$stepKey}' cần hoàn thành bước '{$depKey}' trước " .
                    "(artifacts['{$depKey}'] chưa được gửi kèm)."
                );
            }

            $depStep = $this->step($depKey);
            $artifact = $artifacts[$depKey];
            if (! is_array($artifact) || ! $depStep->validateArtifact($artifact)) {
                throw new InvalidPipelineException(
                    "Kết quả của bước '{$depKey}' (artifacts['{$depKey}']) không " .
                    "hợp lệ để bước '{$stepKey}' sử dụng."
                );
            }
        }
    }
}
