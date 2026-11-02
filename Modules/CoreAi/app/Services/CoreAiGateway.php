<?php

namespace Modules\CoreAi\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Exceptions\AiValidationException;
use Modules\CoreAi\Models\AiRun;
use Modules\CoreAi\Models\AiRunStep;
use Modules\CoreAi\Registries\AiDriverRegistry;
use Modules\TaskCenter\Models\AiUsageLog;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Triển khai cổng gọi AI trung tâm toàn hệ thống.
 * =====================================================================
 *
 * Luồng của MỌI lệnh AI (structured/image/speech/transcribe):
 *   1. Tạo `AiRun` (status=queued) kèm connection_snapshot an toàn.
 *   2. ModelResolver chọn provider+model theo capability (+hint).
 *   3. Driver (AiDriverRegistry) gọi HTTP — key rotation/SSRF/timeout.
 *   4. markRunning → gọi → markReady(output)/markFailed(code,msg).
 *   5. Ghi 1 `AiRunStep` (step_key=operation) + `AiUsageLog` (token/cost).
 *   6. Trả Response DTO cho caller.
 *
 * Bất kỳ lỗi nào cũng được bắt: run markFailed + diagnostics, rồi ném lại
 * `AiGatewayException`/`AiValidationException` để caller xử lý.
 *
 * `lastRun()` trả về `AiRun` vừa ghi của lệnh gần nhất — caller lấy nó để
 * liên kết `ai_provenances` / `ai_run_id` trên domain model.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - executeStructured() : text/JSON/Vision.
 * - generateImage()     : sinh ảnh.
 * - synthesizeSpeech()  : TTS.
 * - transcribeAudio()   : STT.
 * - lastRun()           : AiRun của lệnh vừa chạy.
 * - run()               : pipeline chung (resolve→driver→step→usage→ready).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request DTO (có module/operation/providerKey/modelId).
 * - OUTPUT: Response DTO; side effect = ai_runs/ai_run_steps/ai_usage_logs.
 * =====================================================================
 */
class CoreAiGateway implements CoreAiGatewayContract
{
    /** AiRun của lệnh gọi gần nhất — cho caller liên kết provenance/audit. */
    protected ?AiRun $lastRun = null;

    public function __construct(
        protected readonly ModelResolver $resolver,
        protected readonly AiDriverRegistry $drivers,
    ) {}

    /** INPUT: không. OUTPUT: ?AiRun — run vừa ghi của lệnh gần nhất. */
    public function lastRun(): ?AiRun
    {
        return $this->lastRun;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lệnh text/structured/vision qua capability resolve.
     * =====================================================================
     */
    public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
    {
        return $this->run(
            $req,
            $req->requiredCapability(),
            'structured',
            fn ($driver, $resolved) => $driver->structured($resolved, $req),
            fn ($res) => $res->toRunOutput(),
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lệnh sinh ảnh (image_generation capability).
     * =====================================================================
     */
    public function generateImage(AiImageRequest $req): AiImageResponse
    {
        return $this->run(
            $req,
            $req->requiredCapability(),
            'image',
            fn ($driver, $resolved) => $driver->image($resolved, $req),
            fn ($res) => $res->toRunOutput(),
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lệnh TTS (tts capability).
     * =====================================================================
     */
    public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
    {
        return $this->run(
            $req,
            $req->requiredCapability(),
            'speech',
            fn ($driver, $resolved) => $driver->speech($resolved, $req),
            fn ($res) => $res->toRunOutput(),
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lệnh STT (stt capability).
     * =====================================================================
     */
    public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
    {
        return $this->run(
            $req,
            $req->requiredCapability(),
            'transcribe',
            fn ($driver, $resolved) => $driver->transcribe($resolved, $req),
            fn ($res) => $res->toRunOutput(),
        );
    }

    /* =====================================================================
     * Pipeline chung
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Pipeline đầy đủ của một lệnh AI.
     * =====================================================================
     * INPUT :
     *   - object $req            — Request DTO (có ->module/->operation/
     *                              ->providerKey/->modelId/->toRunInput()).
     *   - string $capability     — AiModel::CAP_* cần thiết.
     *   - string $stepKey        — key bước ghi vào ai_run_steps.
     *   - callable $call         — fn(driver, AiResolvedModelDto):Response DTO.
     *   - callable $toRunOutput  — fn(Response DTO):array cho output_json.
     *
     * OUTPUT: Response DTO của driver.
     * EXCEPTION: AiGatewayException|AiValidationException (run đã markFailed).
     * SIDE EFFECT: INSERT/UPDATE ai_runs + ai_run_steps + ai_usage_logs.
     * =====================================================================
     */
    protected function run(
        object $req,
        string $capability,
        string $stepKey,
        callable $call,
        callable $toRunOutput,
    ) {
        $module = (string) ($req->module ?? 'core');
        $operation = (string) ($req->operation ?? $stepKey);
        $runInput = method_exists($req, 'toRunInput') ? $req->toRunInput() : [];

        // 1. Tạo AiRun queued — connection_snapshot bổ sung sau khi resolve.
        $run = AiRun::create([
            'module' => $module,
            'operation' => $operation,
            'input_json' => $runInput,
            'initiated_by' => $this->currentUserId(),
        ]);
        $this->lastRun = $run;

        // Step duy nhất của lệnh — ghi input_hash để idempotent/trace.
        $step = AiRunStep::create([
            'ai_run_id' => $run->id,
            'step_key' => $stepKey,
            'input_hash' => AiRunStep::hashInput($runInput),
            'status' => AiRunStep::STATUS_QUEUED,
        ]);

        $started = microtime(true);
        try {
            $run->markRunning();
            $step->markRunning();

            // 2. Resolve provider+model theo capability (+ hint).
            $resolved = $this->resolver->resolve(
                $capability,
                $req->providerKey ?? null,
                $req->modelId ?? null,
            );

            // 3. Cập nhật connection snapshot (an toàn — không chứa key).
            $run->connection_snapshot_json = $resolved->connectionSnapshot();
            $run->save();

            // 4. Gọi driver — key rotation xử lý bên trong driver.
            $driver = $this->drivers->resolve($resolved->provider);
            $response = $call($driver, $resolved);

            $latency = (int) round((microtime(true) - $started) * 1000);

            // 5. Ghi step + usage + markReady.
            $output = $toRunOutput($response);
            $step->markReady($output, $response->latencyMs ?? $latency);
            $run->markReady($output);
            $this->recordUsage($resolved, $response, $run);

            return $response;
        } catch (AiGatewayException|\Throwable $e) {
            $latency = (int) round((microtime(true) - $started) * 1000);
            $this->handleFailure($e, $run, $step, $resolved ?? null, $latency);
            throw $e;
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi 1 dòng ai_usage_logs (token/cost ước tính).
     * =====================================================================
     * INPUT: resolved + response + run. OUTPUT: void.
     * SIDE EFFECT: INSERT ai_usage_logs. Lỗi ghi usage không làm hỏng run.
     * =====================================================================
     */
    protected function recordUsage(
        AiResolvedModelDto $resolved,
        object $response,
        AiRun $run,
    ): void {
        try {
            $usage = $response->usage ?? [];
            $in = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
            $out = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
            $cost = $resolved->model->estimateCostUsd($in, $out);

            AiUsageLog::create([
                'ai_run_id' => $run->id,
                'provider' => $resolved->provider->key,
                'model' => $resolved->model->remote_model_id,
                'capability' => $this->usageCapability($resolved),
                'input_units' => $in,
                'output_units' => $out,
                'estimated_cost_usd' => $cost,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('core-ai usage log failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa capability model sang nhãn ai_usage_logs.
     * =====================================================================
     * INPUT: resolved. OUTPUT: string 'text|vision|image|tts|stt'.
     * =====================================================================
     */
    protected function usageCapability(AiResolvedModelDto $resolved): string
    {
        $caps = $resolved->model->capabilities();
        foreach ([
            'structured_output' => AiUsageLog::CAP_TEXT,
            'text_generation' => AiUsageLog::CAP_TEXT,
            'vision' => AiUsageLog::CAP_VISION,
            'image_generation' => AiUsageLog::CAP_IMAGE,
            'tts' => AiUsageLog::CAP_TTS,
            'stt' => AiUsageLog::CAP_STT,
        ] as $cap => $label) {
            if (in_array($cap, $caps, true)) {
                return $label;
            }
        }

        return AiUsageLog::CAP_TEXT;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: markFailed run+step và đính diagnostics an toàn.
     * =====================================================================
     * INPUT: exception + run + step + ?resolved + latency.
     * OUTPUT: void. SIDE EFFECT: UPDATE ai_runs/ai_run_steps.
     * =====================================================================
     */
    protected function handleFailure(
        \Throwable $e,
        AiRun $run,
        AiRunStep $step,
        ?AiResolvedModelDto $resolved,
        int $latency,
    ): void {
        $code = $e instanceof AiGatewayException ? $e->errorCode() : 'provider_error';
        $context = $e instanceof AiGatewayException ? $e->context() : [];

        $diagnostics = array_merge($context, [
            'exception' => get_class($e),
            'latency_ms' => $latency,
            'key_index' => $resolved?->keyIndex,
            'errors' => $e instanceof AiValidationException ? $e->errors() : null,
        ]);

        $step->markFailed($diagnostics);
        $run->diagnostics_json = $diagnostics;
        $run->markFailed($code, $e->getMessage());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: id user đăng nhập cho initiated_by (null khi job/console).
     * INPUT: không. OUTPUT: ?int.
     * =====================================================================
     */
    protected function currentUserId(): ?int
    {
        $id = auth()->id();
        return $id === null ? null : (int) $id;
    }
}
