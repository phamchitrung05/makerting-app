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
    public static function success(mixed $data = [], string $message = 'Thao tác thành công', array $meta = []): JsonResponse
    {
        return self::envelope(true, $message, $data, [], $meta, Response::HTTP_OK);
    }

    public static function created(mixed $data = [], string $message = 'Tạo mới thành công', array $meta = []): JsonResponse
    {
        return self::envelope(true, $message, $data, [], $meta, Response::HTTP_CREATED);
    }

    public static function error(string $message = 'Đã xảy ra lỗi', int $status = Response::HTTP_BAD_REQUEST, array $errors = [], array $meta = []): JsonResponse
    {
        return self::envelope(false, $message, null, $errors, $meta, $status);
    }

    public static function validation(array|MessageBag $errors, string $message = 'Dữ liệu không hợp lệ'): JsonResponse
    {
        $normalized = $errors instanceof MessageBag ? $errors->toArray() : $errors;

        return self::envelope(false, $message, null, $normalized, [], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function paginated(LengthAwarePaginator $paginator, string $message = 'Lấy danh sách thành công'): JsonResponse
    {
        return self::envelope(true, $message, $paginator->items(), [], ['pagination' => self::paginationMeta($paginator)], Response::HTTP_OK);
    }

    public static function dataTable(LengthAwarePaginator $paginator, string $message = 'Lấy dữ liệu thành công'): JsonResponse
    {
        $data = ['items' => $paginator->items(), 'itemsLength' => $paginator->total()];

        return self::envelope(true, $message, $data, [], ['pagination' => self::paginationMeta($paginator)], Response::HTTP_OK);
    }

    public static function accepted(mixed $data = [], string $message = 'Đã tiếp nhận, đang xử lý nền', array $meta = []): JsonResponse
    {
        return self::envelope(true, $message, $data, [], $meta, Response::HTTP_ACCEPTED);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

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

    protected static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

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
