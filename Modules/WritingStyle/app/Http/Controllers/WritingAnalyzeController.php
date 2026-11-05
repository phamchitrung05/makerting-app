<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\Http\Requests\AnalyzeTextRequest;
use Modules\WritingStyle\Http\Resources\WritingAnalysisResource;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Endpoint giải mã DNA — dispatch job + đọc kết quả.
 * =====================================================================
 *
 * Cung cấp 2 API cho luồng giải mã văn phong bất đồng bộ:
 * - POST /v1/admin/writing-style/writing-profiles/analyze       -> 202
 * - GET  /v1/admin/writing-style/writing-profiles/analyses/{id} -> 200
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(WritingStyleAnalyzerService $analyzer)
 * - store(AnalyzeTextRequest $request): tạo analysis + taskRun + dispatch job, trả 202
 * - show(string $analysis): đọc trạng thái + kết quả 1 lần giải mã (FE poll)
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AnalyzeTextRequest (source_type, reference_text, name), UUID analysis
 * - OUTPUT: 202 BaseResponse::accepted hoặc 200 BaseResponse::success kèm WritingAnalysisResource
 * =====================================================================
 */
class WritingAnalyzeController extends Controller
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo controller với WritingStyleAnalyzerService
     * =====================================================================
     *
     * INPUT:
     * - WritingStyleAnalyzerService $analyzer: Service xử lý nghiệp vụ giải mã
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Tiêm phụ thuộc WritingStyleAnalyzerService
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function __construct(
        protected readonly WritingStyleAnalyzerService $analyzer,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Tiếp nhận văn bản mẫu, tạo bản ghi phân tích và dispatch job ngầm
     * =====================================================================
     *
     * INPUT:
     * - AnalyzeTextRequest $request: Dữ liệu nguồn (source_type, source_url, reference_text, name)
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 202 BaseResponse::accepted {analysis_id, task_run_id, status}
     *
     * SIDE EFFECT:
     * - Tạo bản ghi WritingProfileAnalysis (pending) và SystemTaskRun trong DB
     * - Dispatch job AnalyzeWritingProfileJob vào queue `ai-text`
     *
     * EXCEPTION/TRANSACTION:
     * - Trả 422 nếu reference_text rỗng (tạm thời chưa hỗ trợ fetcher URL/file)
     * - Quyền yêu cầu: writing.evaluate
     * =====================================================================
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
            'task_run_id' => $out['taskRun']?->id,
            'status'      => $out['analysis']->status,
            'message'     => 'Đã tiếp nhận — đang giải mã văn phong trong nền',
        ], 'Đã tiếp nhận yêu cầu giải mã văn phong');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đọc trạng thái và toàn bộ kết quả của 1 lần giải mã văn phong
     * =====================================================================
     *
     * INPUT:
     * - string $analysis: UUID của bản ghi WritingProfileAnalysis
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 200 BaseResponse::success(WritingAnalysisResource)
     *
     * SIDE EFFECT:
     * - Truy vấn DB bảng writing_profile_analyses kèm eager loading draftProfile
     *
     * EXCEPTION/TRANSACTION:
     * - 404 ModelNotFoundException nếu UUID không tồn tại
     * - Quyền yêu cầu: writing.profile.view
     * =====================================================================
     */
    public function show(string $analysis)
    {
        $model = WritingProfileAnalysis::with('draftProfile')->findOrFail($analysis);

        return BaseResponse::success(new WritingAnalysisResource($model));
    }
}
