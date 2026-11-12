<?php

namespace Modules\CoreAi\Tests\Unit;

use Modules\Core\Exceptions\RemoteFetchException;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Services\AiProviderClient;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Kiểm thử HTTP client AI không đổi thời gian sống của CLI
 * =====================================================================
 *
 * Queue worker dùng lại process sau mỗi job. Request AI không được để lại
 * max_execution_time hữu hạn khiến worker chết khi đang chờ job kế tiếp.
 * Dùng URL sai scheme để dừng trước DNS/cURL và không gọi provider thật.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - test_cli_request_preserves_unlimited_execution_time(): giữ giới hạn 0.
 * - test_cli_request_preserves_callers_finite_execution_time(): giữ giới hạn
 *   hữu hạn nếu chính caller CLI chủ động đặt, không đổi thành timeout HTTP.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Provider chưa lưu, URL bị chặn và max_execution_time của process.
 * - OUTPUT: Assertions về giới hạn PHP sau request; không gọi mạng/ghi DB.
 * =====================================================================
 */
class AiProviderClientExecutionTimeTest extends TestCase
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng request AI không biến worker CLI thành giới hạn 210s
     * =====================================================================
     * INPUT: max_execution_time=0, HTTP timeout 180s, URL không dùng HTTP/HTTPS.
     * OUTPUT: void; URL bị chặn nhưng giới hạn PHP vẫn bằng 0.
     * SIDE EFFECT: Tạm đổi giới hạn PHP của test, khôi phục trong finally;
     * không thực hiện DNS/cURL và không tạo provider trong DB.
     * EXCEPTION/TRANSACTION: RemoteFetchException là kết quả mong đợi;
     * AssertionFailedError nếu giới hạn bị thay đổi. Không mở transaction.
     * =====================================================================
     */
    public function test_cli_request_preserves_unlimited_execution_time(): void
    {
        if (PHP_SAPI !== 'cli') {
            $this->markTestSkipped('Ca kiểm thử dành cho tiến trình CLI.');
        }

        $previousLimit = (int) ini_get('max_execution_time');
        try {
            set_time_limit(0);
            try {
                (new AiProviderClient)->request(
                    new AiProvider(['request_timeout' => 180]),
                    'POST',
                    'ftp://invalid.example',
                );
                $this->fail('URL sai scheme phải bị chặn trước khi gọi mạng.');
            } catch (RemoteFetchException) {
                $this->assertSame(0, (int) ini_get('max_execution_time'));
            }
        } finally {
            set_time_limit($previousLimit);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng HTTP client không ghi đè ngân sách của caller CLI
     * =====================================================================
     * INPUT: Caller đặt 47s, HTTP timeout 180s, URL sai scheme.
     * OUTPUT: void; giới hạn caller vẫn là 47s sau khi request bị chặn.
     * SIDE EFFECT: Tạm đổi và khôi phục giới hạn PHP; không gọi mạng/ghi DB.
     * EXCEPTION/TRANSACTION: RemoteFetchException là kết quả mong đợi;
     * AssertionFailedError nếu client đổi ngân sách CLI. Không mở transaction.
     * =====================================================================
     */
    public function test_cli_request_preserves_callers_finite_execution_time(): void
    {
        if (PHP_SAPI !== 'cli') {
            $this->markTestSkipped('Ca kiểm thử dành cho tiến trình CLI.');
        }

        $previousLimit = (int) ini_get('max_execution_time');
        try {
            set_time_limit(47);
            try {
                (new AiProviderClient)->request(
                    new AiProvider(['request_timeout' => 180]),
                    'POST',
                    'ftp://invalid.example',
                );
                $this->fail('URL sai scheme phải bị chặn trước khi gọi mạng.');
            } catch (RemoteFetchException) {
                $this->assertSame(47, (int) ini_get('max_execution_time'));
            }
        } finally {
            set_time_limit($previousLimit);
        }
    }
}
