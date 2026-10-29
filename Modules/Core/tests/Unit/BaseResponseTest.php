<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Http\Responses\BaseResponse;
use PHPUnit\Framework\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test envelope JSON của BaseResponse (không cần Laravel).
 * =====================================================================
 */
class BaseResponseTest extends TestCase
{
    public function test_success_envelope(): void
    {
        $res = BaseResponse::success(['id' => 1], 'OK');
        $data = $res->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame('OK', $data['message']);
        $this->assertSame(['id' => 1], $data['data']);
        $this->assertSame([], $data['errors']);
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_error_envelope(): void
    {
        $res = BaseResponse::error('Bad', 400, ['field' => ['loi']]);
        $data = $res->getData(true);

        $this->assertFalse($data['success']);
        $this->assertNull($data['data']);
        $this->assertSame(['field' => ['loi']], $data['errors']);
        $this->assertSame(400, $res->getStatusCode());
    }

    public function test_validation_status_422(): void
    {
        $res = BaseResponse::validation(['email' => ['Bắt buộc']]);
        $this->assertSame(422, $res->getStatusCode());
        $this->assertFalse($res->getData(true)['success']);
    }

    public function test_no_content_204(): void
    {
        $res = BaseResponse::noContent();
        $this->assertSame(204, $res->getStatusCode());
    }

    public function test_accepted_202(): void
    {
        $res = BaseResponse::accepted(['task' => 'abc']);
        $this->assertSame(202, $res->getStatusCode());
        $this->assertTrue($res->getData(true)['success']);
    }
}
