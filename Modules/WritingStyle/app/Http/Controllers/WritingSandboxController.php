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
     * INPUT: topic + ?target_tier + ?target_sentences.
     * OUTPUT: {text, target_tier, snapshot, ai_run_id}.
     */
    public function store(SandboxRequest $request, WritingProfile $writingProfile)
    {
        $data = $request->validated();
        $result = $this->sandbox->sandbox(
            $writingProfile,
            $data['topic'],
            $data['target_tier'] ?? $writingProfile->dominant_tier,
            (int) ($data['target_sentences'] ?? 0),
        );

        return BaseResponse::success($result->toArray(), 'Viết thử xong');
    }
}
