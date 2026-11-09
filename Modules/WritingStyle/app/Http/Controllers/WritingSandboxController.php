<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\Http\Requests\RunSandboxStepRequest;
use Modules\WritingStyle\Http\Requests\SandboxRequest;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\SandboxPipelineRunner;
use Modules\WritingStyle\Services\WritingStyleSandboxService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Endpoint DNA Sandbox — viết thử & quản lý bản viết thử đã lưu.
 * =====================================================================
 * - POST   /v1/admin/writing-style/writing-profiles/{profile}/sandbox
 * - POST   /v1/admin/writing-style/writing-profiles/{profile}/sandbox/steps/{step}
 * - POST   /v1/admin/writing-style/writing-profiles/{profile}/sandbox-samples
 * - DELETE /v1/admin/writing-style/writing-profiles/{profile}/sandbox-samples/{sampleId}
 * =====================================================================
 */
class WritingSandboxController extends Controller
{
    public function __construct(
        protected readonly WritingStyleSandboxService $sandbox,
        protected readonly SandboxPipelineRunner $pipelineRunner,
    ) {}

    /**
     * POST /sandbox — viết thử từ DNA + topic + tier.
     * INPUT: topic + ?target_tier + ?target_sentences + runtime overrides.
     * OUTPUT: {text, target_tier, snapshot, ai_run_id}.
     */
    public function store(SandboxRequest $request, WritingProfile $writingProfile)
    {
        // Mở rộng giới hạn thời gian thực thi PHP cho các bài viết dài (tier long
        // có thể cần 80–120s để AI sinh ~3.000 tokens, tránh lỗi FatalError 60s).
        @set_time_limit(180);

        $data = $request->validated();

        // Gói overrides cho SnapshotService — giữ nhẹ (chỉ key tồn tại).
        $overrides = array_filter([
            'tone_overrides'             => $data['tone_overrides'] ?? null,
            'style_instructions'         => $data['style_instructions'] ?? null,
            'voice_texture_instructions' => $data['voice_texture_instructions'] ?? null,
            'tone_instructions'          => $data['tone_instructions'] ?? null,
            'voice_texture_sliders_json' => $data['voice_texture_sliders_json'] ?? null,
            'temperature'                => isset($data['temperature']) ? (float) $data['temperature'] : null,
        ], static fn ($v) => $v !== null);

        $result = $this->sandbox->sandbox(
            $writingProfile,
            $data['topic'],
            $data['target_tier'] ?? $writingProfile->dominant_tier,
            (int) ($data['target_sentences'] ?? 0),
            $overrides,
        );

        return BaseResponse::success($result->toArray(), 'Viết thử xong');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Thực thi 1 step trong Sandbox Pipeline theo tier.
     * =====================================================================
     * POST /writing-profiles/{profile}/sandbox/steps/{step}
     * =====================================================================
     */
    public function runStep(RunSandboxStepRequest $request, WritingProfile $writingProfile, string $step)
    {
        @set_time_limit(180);

        $data = $request->validated();

        $overrides = array_filter([
            'tone_overrides'             => $data['tone_overrides'] ?? null,
            'style_instructions'         => $data['style_instructions'] ?? null,
            'voice_texture_instructions' => $data['voice_texture_instructions'] ?? null,
            'tone_instructions'          => $data['tone_instructions'] ?? null,
            'voice_texture_sliders_json' => $data['voice_texture_sliders_json'] ?? null,
            'temperature'                => isset($data['temperature']) ? (float) $data['temperature'] : null,
            'target_sentences'           => isset($data['target_sentences']) ? (int) $data['target_sentences'] : null,
        ], static fn ($v) => $v !== null);

        $result = $this->pipelineRunner->runStep(
            $writingProfile,
            $data['target_tier'] ?? $writingProfile->dominant_tier,
            $step,
            $data['topic'],
            (array) ($data['artifacts'] ?? []),
            $overrides,
        );

        return BaseResponse::success($result, 'Đã chạy xong bước ' . $step);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lưu (Ghim) 1 bản viết thử vào Database của hồ sơ văn phong.
     * =====================================================================
     * POST /writing-profiles/{profile}/sandbox-samples
     *
     * INPUT: { topic, target_tier, text, system_prompt? }
     * OUTPUT: { samples: array, sample: array }
     * =====================================================================
     */
    public function saveSample(Request $request, WritingProfile $writingProfile)
    {
        $data = $request->validate([
            'topic'         => ['required', 'string', 'max:500'],
            'target_tier'   => ['nullable', 'string', 'in:micro,short,medium,long'],
            'text'          => ['required', 'string', 'max:50000'],
            'system_prompt' => ['nullable', 'string', 'max:50000'],
        ]);

        $samples = is_array($writingProfile->sandbox_samples_json)
            ? $writingProfile->sandbox_samples_json
            : [];

        $newSample = [
            'id'            => (string) Str::uuid(),
            'topic'         => trim($data['topic']),
            'target_tier'   => $data['target_tier'] ?? $writingProfile->dominant_tier ?? 'medium',
            'text'          => trim($data['text']),
            'system_prompt' => isset($data['system_prompt']) ? trim($data['system_prompt']) : '',
            'created_at'    => now()->toISOString(),
        ];

        array_unshift($samples, $newSample);

        // Giữ tối đa 30 bản viết thử gần nhất để tối ưu dung lượng JSON
        $samples = array_slice($samples, 0, 30);

        $writingProfile->update([
            'sandbox_samples_json' => $samples,
        ]);

        return BaseResponse::success([
            'sample'  => $newSample,
            'samples' => $samples,
        ], 'Đã lưu bản viết thử vào hồ sơ');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa 1 bản viết thử đã lưu khỏi Database của hồ sơ văn phong.
     * =====================================================================
     * DELETE /writing-profiles/{profile}/sandbox-samples/{sampleId}
     *
     * INPUT: route params {writing_profile}, {sampleId}
     * OUTPUT: { samples: array }
     * =====================================================================
     */
    public function deleteSample(WritingProfile $writingProfile, string $sampleId)
    {
        $samples = is_array($writingProfile->sandbox_samples_json)
            ? $writingProfile->sandbox_samples_json
            : [];

        $filtered = array_values(array_filter(
            $samples,
            static fn ($item) => (string) ($item['id'] ?? '') !== $sampleId
        ));

        $writingProfile->update([
            'sandbox_samples_json' => $filtered,
        ]);

        return BaseResponse::success([
            'samples' => $filtered,
        ], 'Đã xóa bản viết thử');
    }
}
