<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;
use Modules\Core\Models\IdempotencyKey;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test IdempotencyMiddleware — các nhánh replay/lock/zombie.
 * =====================================================================
 */
class IdempotencyMiddlewareTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * CHỨC NĂNG: Tạo request giả có header Idempotency-Key.
     * INPUT : key, method, path, body, user_id.
     * OUTPUT: Illuminate\Http\Request đã set header.
     */
    private function makeRequest(string $key, string $method = 'POST', string $path = '/api/v1/admin/x', string $body = '{}', ?int $userId = null): Request
    {
        $req = Request::create($path, $method, [], [], [], [], $body);
        $req->headers->set('Idempotency-Key', $key);
        if ($userId !== null) {
            $req->setUserResolver(fn () => (object) ['id' => $userId]);
        }

        return $req;
    }

    /**
     * CHỨC NĂNG: Trả response 200 thành công giả.
     * INPUT : body array. OUTPUT: JsonResponse 200.
     */
    private function okResponse(array $body = ['ok' => true]): JsonResponse
    {
        return response()->json($body, 200);
    }

    public function test_khong_co_header_chay_thang(): void
    {
        $middleware = new IdempotencyMiddleware();
        $req = Request::create('/x', 'GET');
        $response = $middleware->handle($req, fn () => $this->okResponse());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_tao_key_va_luu_response_thanh_cong(): void
    {
        $middleware = new IdempotencyMiddleware();
        $req = $this->makeRequest('abc-key-1', 'POST', '/api/v1/admin/x', '{"a":1}');

        $response = $middleware->handle($req, fn () => $this->okResponse(['done' => 1]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, IdempotencyKey::count());

        $record = IdempotencyKey::find('abc-key-1');
        $this->assertSame(200, $record->response_status);
        $this->assertSame(['done' => 1], $record->response_body_json);
        $this->assertTrue($record->isCompleted());
    }

    public function test_replay_khi_key_da_hoan_tat(): void
    {
        IdempotencyKey::create([
            'key' => 'replay-key',
            'user_id' => 1,
            'route_signature' => 'POST|api/v1/admin/x',
            'request_hash' => hash('sha256', '{}'),
            'response_status' => 200,
            'response_body_json' => ['cached' => true],
            'locked_until' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $req = $this->makeRequest('replay-key', 'POST', '/api/v1/admin/x', '{}', 1);
        $middleware = new IdempotencyMiddleware();

        $controllerCalled = false;
        $response = $middleware->handle($req, function () use (&$controllerCalled) {
            $controllerCalled = true;
            return $this->okResponse();
        });

        $this->assertFalse($controllerCalled);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->headers->get('Idempotency-Replayed'));
        $this->assertSame(['cached' => true], json_decode($response->getContent(), true));
    }

    public function test_chan_khi_signature_khac(): void
    {
        IdempotencyKey::create([
            'key' => 'diff-key',
            'user_id' => null,
            'route_signature' => 'POST|api/v1/admin/x',
            'request_hash' => hash('sha256', '{}'),
            'locked_until' => now()->addMinute(),
            'expires_at' => now()->addHour(),
        ]);

        // Cùng key nhưng khác route_signature.
        $req = $this->makeRequest('diff-key', 'POST', '/api/v1/admin/khac', '{}');
        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($req, fn () => $this->okResponse());

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_409_khi_dang_locked(): void
    {
        IdempotencyKey::create([
            'key' => 'locked-key',
            'user_id' => null,
            'route_signature' => 'POST|api/v1/admin/x',
            'request_hash' => hash('sha256', '{}'),
            'locked_until' => now()->addMinute(),
            'expires_at' => now()->addHour(),
        ]);

        $req = $this->makeRequest('locked-key', 'POST', '/api/v1/admin/x', '{}');
        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($req, fn () => $this->okResponse());

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_thu_hoi_key_zombie_va_lam_lai(): void
    {
        // Zombie: hết locked_until nhưng chưa completed (worker chết giữa chừng).
        IdempotencyKey::create([
            'key' => 'zombie-key',
            'user_id' => null,
            'route_signature' => 'POST|api/v1/admin/x',
            'request_hash' => hash('sha256', '{}'),
            'locked_until' => now()->subMinute(), // đã hết hạn
            'expires_at' => now()->addHour(),
        ]);

        $req = $this->makeRequest('zombie-key', 'POST', '/api/v1/admin/x', '{}');
        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($req, fn () => $this->okResponse(['fresh' => true]));

        $this->assertSame(200, $response->getStatusCode());
        // Key zombie cũ đã bị xóa và tạo bản mới completed.
        $this->assertSame(1, IdempotencyKey::count());
        $this->assertTrue(IdempotencyKey::find('zombie-key')->isCompleted());
    }

    public function test_403_khi_user_khac_replay(): void
    {
        IdempotencyKey::create([
            'key' => 'user-key',
            'user_id' => 1, // của user 1
            'route_signature' => 'POST|api/v1/admin/x',
            'request_hash' => hash('sha256', '{}'),
            'response_status' => 200,
            'response_body_json' => ['secret' => 'data'],
            'locked_until' => now(),
            'expires_at' => now()->addHour(),
        ]);

        // User 2 dùng lại key của user 1.
        $req = $this->makeRequest('user-key', 'POST', '/api/v1/admin/x', '{}', 2);
        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($req, fn () => $this->okResponse());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_giai_phong_key_khi_controller_nem_exception(): void
    {
        $req = $this->makeRequest('ex-key', 'POST', '/api/v1/admin/x', '{}');
        $middleware = new IdempotencyMiddleware();

        try {
            $middleware->handle($req, function () {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], ['x' => 'required'])
                );
            });
            $this->fail('Phải ném ValidationException');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Đúng — nhưng key phải đã bị xóa, không kẹt 409.
        }

        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_xoa_key_khi_response_loi(): void
    {
        $req = $this->makeRequest('err-key', 'POST', '/api/v1/admin/x', '{}');
        $middleware = new IdempotencyMiddleware();

        $response = $middleware->handle($req, fn () => response()->json(['err' => 1], 500));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_tu_dong_xoa_key_het_ttl(): void
    {
        IdempotencyKey::create([
            'key' => 'expired-key',
            'user_id' => null,
            'route_signature' => 'POST|api/v1/admin/x',
            'request_hash' => hash('sha256', '{}'),
            'locked_until' => now()->subDay(),
            'expires_at' => now()->subMinute(), // hết TTL
        ]);

        $req = $this->makeRequest('expired-key', 'POST', '/api/v1/admin/x', '{}');
        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($req, fn () => $this->okResponse());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(IdempotencyKey::find('expired-key')->isCompleted());
    }
}
