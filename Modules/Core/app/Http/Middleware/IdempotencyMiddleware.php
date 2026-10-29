<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Core\Models\IdempotencyKey;
use Symfony\Component\HttpFoundation\Response;

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
    protected const LOCK_SECONDS = 60;
    protected const TTL_SECONDS = 86400; // 24h

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

        $signature = $request->method() . '|' . $request->path();
        $hash = hash('sha256', $request->getContent());

        $record = IdempotencyKey::find($key);

        if ($record) {
            if ($record->isExpired()) {
                $record->delete();
                $record = null;
            } elseif ($record->route_signature !== $signature || $record->request_hash !== $hash) {
                return BaseResponse::error(
                    'Idempotency-Key đã được dùng cho một request khác',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } elseif ($record->isCompleted()) {
                return response()->json(
                    $record->response_body_json,
                    $record->response_status
                )->header('Idempotency-Replayed', 'true');
            } elseif ($record->isLocked()) {
                return BaseResponse::error(
                    'Yêu cầu đang được xử lý, vui lòng thử lại sau',
                    Response::HTTP_CONFLICT
                );
            }
        }

        // Đăng ký key mới + khóa.
        $record = IdempotencyKey::create([
            'key' => $key,
            'user_id' => $request->user()?->id,
            'route_signature' => $signature,
            'request_hash' => $hash,
            'locked_until' => now()->addSeconds(self::LOCK_SECONDS),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        /** @var Response $response */
        $response = $next($request);

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
