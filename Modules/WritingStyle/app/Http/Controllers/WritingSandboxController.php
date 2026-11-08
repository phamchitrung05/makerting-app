<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\Http\Requests\SandboxRequest;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingStyleSandboxService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Endpoint DNA Sandbox — viết thử, trả text (không lưu).
 * =====================================================================
 * POST /v1/admin/writing-style/writing-profiles/{profile}/sandbox
 * =====================================================================
 */
class WritingSandboxController extends Controller
{
    public function __construct(
        protected readonly WritingStyleSandboxService $sandbox,
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
}
