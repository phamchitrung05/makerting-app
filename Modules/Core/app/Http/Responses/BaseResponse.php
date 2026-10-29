<?php

namespace Modules\Core\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa envelope JSON trả về cho toàn bộ API Admin.
 * =====================================================================
 * Envelope: { success, message, data, errors, meta }
 * VDataTableServer dùng dataTable() -> data.items + itemsLength + meta.pagination
 * =====================================================================
 */
class BaseResponse
{
    /**
     * CHỨC NĂNG: Trả response 200 OK với payload data tùy ý.
     * INPUT : mixed $data — dữ liệu trả về; string $message; array $meta.
     * OUTPUT: JsonResponse 200 { success:true, message, data, meta }.
     * SIDE EFFECT: không có.
     */
    public static function success(mixed $data = [], string $message = 'Thao tác thành công', array $meta = []): JsonResponse
    {
        return self::envelope(true, $message, $data, [], $meta, Response::HTTP_OK);
    }

    /**
     * CHỨC NĂNG: Trả response 201 Created sau khi tạo tài nguyên.
     * INPUT : mixed $data — bản ghi vừa tạo; string $message; array $meta.
     * OUTPUT: JsonResponse 201 { success:true, message, data, meta }.
     * SIDE EFFECT: không có.
     */
    public static function created(mixed $data = [], string $message = 'Tạo mới thành công', array $meta = []): JsonResponse
    {
        return self::envelope(true, $message, $data, [], $meta, Response::HTTP_CREATED);
    }

    /**
     * CHỨC NĂNG: Trả response lỗi chung (4xx/5xx) với envelope chuẩn.
     * INPUT : string $message; int $status (mặc định 400); array $errors
     *         (lỗi chi tiết, có thể rỗng); array $meta.
     * OUTPUT: JsonResponse { success:false, message, data:null, errors, meta }.
     * SIDE EFFECT: không có.
     */
    public static function error(string $message = 'Đã xảy ra lỗi', int $status = Response::HTTP_BAD_REQUEST, array $errors = [], array $meta = []): JsonResponse
    {
        return self::envelope(false, $message, null, $errors, $meta, $status);
    }

    /**
     * CHỨC NĂNG: Trả response 422 với danh sách lỗi validate theo field.
     * INPUT : array|MessageBag $errors — lỗi Laravel validate; string $message.
     * OUTPUT: JsonResponse 422 { success:false, message, errors: {field:[...]} }.
     * SIDE EFFECT: không có.
     */
    public static function validation(array|MessageBag $errors, string $message = 'Dữ liệu không hợp lệ'): JsonResponse
    {
        $normalized = $errors instanceof MessageBag ? $errors->toArray() : $errors;

        return self::envelope(false, $message, null, $normalized, [], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * CHỨC NĂNG: Trả danh sách phân trang chuẩn (items + pagination meta).
     * INPUT : LengthAwarePaginator $paginator; string $message.
     * OUTPUT: JsonResponse 200 { data: [...items], meta.pagination: {...} }.
     * SIDE EFFECT: không có.
     */
    public static function paginated(LengthAwarePaginator $paginator, string $message = 'Lấy danh sách thành công'): JsonResponse
    {
        return self::envelope(true, $message, $paginator->items(), [], ['pagination' => self::paginationMeta($paginator)], Response::HTTP_OK);
    }

    /**
     * CHỨC NĂNG: Trả dữ liệu cho Vuetify VDataTableServer.
     * INPUT : LengthAwarePaginator $paginator; string $message.
     * OUTPUT: JsonResponse 200 { data: { items, itemsLength }, meta.pagination }.
     * SIDE EFFECT: không có.
     */
    public static function dataTable(LengthAwarePaginator $paginator, string $message = 'Lấy dữ liệu thành công'): JsonResponse
    {
        $data = ['items' => $paginator->items(), 'itemsLength' => $paginator->total()];

        return self::envelope(true, $message, $data, [], ['pagination' => self::paginationMeta($paginator)], Response::HTTP_OK);
    }

    /**
     * CHỨC NĂNG: Trả response 202 Accepted cho tác vụ queue bất đồng bộ.
     * INPUT : mixed $data — thường là task_id/job_uuid để client theo dõi;
     *         string $message; array $meta.
     * OUTPUT: JsonResponse 202 { success:true, message, data, meta }.
     * SIDE EFFECT: không có.
     */
    public static function accepted(mixed $data = [], string $message = 'Đã tiếp nhận, đang xử lý nền', array $meta = []): JsonResponse
    {
        return self::envelope(true, $message, $data, [], $meta, Response::HTTP_ACCEPTED);
    }

    /**
     * CHỨC NĂNG: Trả response 204 No Content (xóa thành công, không payload).
     * INPUT : không có. OUTPUT: JsonResponse 204 rỗng.
     * SIDE EFFECT: không có.
     */
    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * CHỨC NĂNG: Map exception Laravel ném ra thành JsonResponse chuẩn.
     * INPUT : Throwable $e — ValidationException, AuthenticationException,
     *         AuthorizationException, ModelNotFoundException, HttpException
     *         hoặc lỗi 500 bất kỳ.
     * OUTPUT: JsonResponse tương ứng 422/401/403/404/4xx/500.
     * SIDE EFFECT: không có (gọi config('app.debug') để quyết định lộ message).
     */
    public static function fromException(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof \Illuminate\Validation\ValidationException =>
                self::validation($e->errors(), $e->getMessage() !== '' ? $e->getMessage() : 'Dữ liệu không hợp lệ'),
            $e instanceof \Illuminate\Auth\AuthenticationException =>
                self::error('Chưa xác thực', Response::HTTP_UNAUTHORIZED),
            $e instanceof \Illuminate\Auth\Access\AuthorizationException =>
                self::error('Không có quyền thực hiện', Response::HTTP_FORBIDDEN),
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException =>
                self::error('Không tìm thấy tài nguyên', Response::HTTP_NOT_FOUND),
            $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface =>
                self::error($e->getMessage() !== '' ? $e->getMessage() : 'Yêu cầu không hợp lệ', $e->getStatusCode()),
            default =>
                self::error(config('app.debug') ? $e->getMessage() : 'Đã xảy ra lỗi hệ thống', Response::HTTP_INTERNAL_SERVER_ERROR),
        };
    }

    /**
     * CHỨC NĂNG: Chuẩn hóa meta pagination từ LengthAwarePaginator.
     * INPUT : LengthAwarePaginator $paginator.
     * OUTPUT: array { current_page, per_page, total, last_page }.
     * SIDE EFFECT: không có.
     */
    protected static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * CHỨC NĂNG: Đóng gói payload vào envelope chuẩn success/message/data/errors/meta.
     * INPUT : bool $success; string $message; mixed $data; array $errors;
     *         array $meta; int $status.
     * OUTPUT: JsonResponse { success, message, data, errors, meta }.
     * SIDE EFFECT: không có.
     */
    protected static function envelope(bool $success, string $message, mixed $data, array $errors, array $meta, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors === [] ? [] : $errors,
            'meta' => $meta,
        ], $status);
    }
}
