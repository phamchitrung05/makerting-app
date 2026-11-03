<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\Http\Requests\AnalyzeTextRequest;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Endpoint giải mã DNA — dispatch job, trả 202.
 * =====================================================================
 * POST /v1/admin/writing-style/writing-profiles/analyze
 * =====================================================================
 */
class WritingAnalyzeController extends Controller
{
    public function __construct(
        protected readonly WritingStyleAnalyzerService $analyzer,
    ) {}

    /**
     * POST /analyze — tạo analysis + taskRun + dispatch job (202).
     * INPUT: source_type(paste|url|file) + ?source_url + ?reference_text + ?name.
     * OUTPUT: 202 {analysis_id, task_run_id, status:'pending'}.
     */
    public function store(AnalyzeTextRequest $request)
    {
        $data = $request->validated();
        $sourceType = $data['source_type'];
        $sourceUrl = $data['source_url'] ?? null;

        // Với source_type=url/file hiện chỉ hỗ trợ paste trực tiếp — url/file
        // cần fetcher riêng (fetch qua SafeRemoteFetcher / đọc file upload),
        // để cho task sau; hiện trả lỗi nghiệp vụ nếu chưa có reference_text.
        $referenceText = (string) ($data['reference_text'] ?? '');
        if ($referenceText === '') {
            return BaseResponse::error(
                'Hiện chỉ hỗ trợ phân tích trực tiếp (paste). Nguồn url/file sẽ bổ sung fetcher sau.',
                422,
                ['reference_text' => ['reference_text bắt buộc cho phân tích nền hiện tại']],
            );
        }

        $out = $this->analyzer->dispatchAnalysis(
            referenceText: $referenceText,
            sourceType: $sourceType,
            sourceUrl: $sourceUrl,
            name: $data['name'] ?? null,
            createdBy: $request->user()?->id,
        );

        return BaseResponse::accepted([
            'analysis_id' => $out['analysis']->id,
            'task_run_id' => $out['taskRun']->id,
            'status'      => $out['analysis']->status,
            'message'     => 'Đã tiếp nhận — đang giải mã văn phong trong nền',
        ], 'Đã tiếp nhận yêu cầu giải mã văn phong');
    }
}
