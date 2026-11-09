<?php

namespace Modules\WritingStyle\Pipelines\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Responses\BaseResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Lỗi vi phạm cấu trúc/phụ thuộc của Sandbox Pipeline.
 * =====================================================================
 *
 * Ném ra khi `PipelineTopologyValidator` bắt gặp:
 *   - config `tier_pipelines` sai thứ tự stage / thiếu bước terminal cuối /
 *     `depends_on` trỏ tới step chưa khai báo phía trước.
 *   - runtime `SandboxPipelineRunner` chạy step mà `artifacts` thiếu hoặc
 *     artifact của bước phụ thuộc không hợp lệ.
 *
 * `render()` tự động serialize về 422 (BaseResponse::error) cho controller.
 * =====================================================================
 */
class InvalidPipelineException extends Exception
{
    /**
     * CHỨC NĂNG: Khởi tạo lỗi pipeline.
     * INPUT: string $message — mô tả tiếng Việt tường minh cho FE hiển thị.
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * CHỨC NĂNG: Render HTTP response 422 khi exception không được bắt.
     * INPUT: Illuminate\Http\Request. OUTPUT: JsonResponse 422 (BaseResponse::error).
     * SIDE EFFECT: Laravel gọi render() khi exception không có handler riêng —
     *             trả luôn response thay vì ném tiếp (ném -> 500).
     */
    public function render(): JsonResponse
    {
        return BaseResponse::error(
            $this->getMessage(),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
