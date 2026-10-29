<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Core\Models\IdempotencyKey;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Middleware chống gọi trùng các request tốn phí (Idempotency).
 * =====================================================================
 * Đọc header `Idempotency-Key`. Nếu key đang xử lý -> 409; nếu đã hoàn tất ->
 * trả lại response đã lưu; nếu mới -> khóa key, chạy controller, lưu response
 * và mở khóa. Chỉ áp cho route khai báo middleware này.
 * =====================================================================
 */
class IdempotencyMiddleware
{
    /**
     * Xử lý request có Idempotency-Key.
     * INPUT: Request, Closure next.
     * OUTPUT: Response — cached/409/fresh.
     * SIDE EFFECT: ghi/xóa bản ghi idempotency_keys.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || strlen($key) > 128) {
            return $next($request);
        }

        $lockSeconds = (int) config('core.idempotency.lock_seconds', 60);
        $ttlSeconds = (int) config('core.idempotency.ttl_seconds', 86400);
        $userId = $request->user()?->id;

        $signature = $request->method() . '|' . $request->path();
        $hash = hash('sha256', $request->getContent());

        $record = IdempotencyKey::find($key);

        if ($record) {
            if ($record->isExpired()) {
                // Key đã hết TTL → xóa và cho phép dùng lại.
                $record->delete();
                $record = null;
            } elseif ($record->route_signature !== $signature || $record->request_hash !== $hash) {
                return BaseResponse::error(
                    'Idempotency-Key đã được dùng cho một request khác',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } elseif ($record->isCompleted()) {
                // Bảo mật: chỉ trả response đã lưu cho đúng user tạo key.
                if ($record->user_id !== null && $record->user_id !== $userId) {
                    return BaseResponse::error(
                        'Idempotency-Key thuộc về người dùng khác',
                        Response::HTTP_FORBIDDEN
                    );
                }

                return response()->json(
                    $record->response_body_json,
                    $record->response_status
                )->header('Idempotency-Replayed', 'true');
            } elseif ($record->isLocked()) {
                return BaseResponse::error(
                    'Yêu cầu đang được xử lý, vui lòng thử lại sau',
                    Response::HTTP_CONFLICT
                );
            } else {
                // Trường hợp treo: request trước hết locked_until nhưng chưa
                // hoàn tất (worker chết giữa chừng) → thu hồi key và làm lại.
                $record->delete();
                $record = null;
            }
        }

        // Đăng ký key mới + khóa. Bắt QueryException khi 2 request cùng mili-giây
        // trượt qua find() rồi đua nhau create() -> trả 409 thay vì 500.
        try {
            $record = IdempotencyKey::create([
                'key' => $key,
                'user_id' => $userId,
                'route_signature' => $signature,
                'request_hash' => $hash,
                'locked_until' => now()->addSeconds($lockSeconds),
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);
        } catch (QueryException $e) {
            // 23000 = integrity constraint violation (trùng PK).
            if ($e->getCode() === '23000') {
                return BaseResponse::error(
                    'Yêu cầu đang được xử lý, vui lòng thử lại sau',
                    Response::HTTP_CONFLICT
                );
            }
            throw $e;
        }

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $e) {
            // Bất kỳ exception nào (ValidationException 422, HttpException 4xx/5xx,
            // lỗi nội bộ...) đều phải giải phóng key để request sau không bị
            // kẹt 409 cho tới khi hết locked_until.
            $record->delete();
            throw $e;
        }

        // Chỉ lưu response thành công (2xx) để replay; lỗi không khóa client.
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $body = json_decode($response->getContent(), true);
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body_json' => $body ?? ['raw' => $response->getContent()],
                'locked_until' => now(),
            ]);
        } else {
            $record->delete();
        }

        return $response;
    }
}
