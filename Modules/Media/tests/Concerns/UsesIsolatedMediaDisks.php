<?php

namespace Modules\Media\Tests\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * =====================================================================
 * CHỨC NĂNG: Cấp disk fake cô lập riêng cho mỗi test case của Media.
 * =====================================================================
 * Tránh dùng chung 1 thư mục `storage/framework/testing/disks/media_public`
 * dẫn tới xung đột xóa/tạo lại thư mục `.../1` trên Windows NTFS khi
 * chạy nối tiếp nhiều test.
 * =====================================================================
 */
trait UsesIsolatedMediaDisks
{
    private ?string $isolatedMediaSuiteRoot = null;

    protected function setUpIsolatedMediaDisks(): void
    {
        $this->isolatedMediaSuiteRoot = storage_path(
            'framework/testing/disks/media-suite/' . bin2hex(random_bytes(8))
        );

        foreach (['media_public', 'media_private', 'temp_processing'] as $disk) {
            $root = $this->isolatedMediaSuiteRoot . DIRECTORY_SEPARATOR . $disk;
            (new Filesystem)->ensureDirectoryExists($root);

            $baseConfig = (array) config("filesystems.disks.{$disk}", []);
            $mergedConfig = array_merge([
                'driver' => 'local',
                'throw' => false,
                'report' => false,
            ], $baseConfig, [
                'root' => $root,
            ]);

            config(["filesystems.disks.{$disk}" => $mergedConfig]);

            $driver = Storage::createLocalDriver($mergedConfig, $disk);
            $driver->buildTemporaryUrlsUsing(
                static fn ($path, $expiration) => URL::to($path . '?expiration=' . $expiration->getTimestamp())
            );

            Storage::set($disk, $driver);
        }
    }

    protected function tearDownIsolatedMediaDisks(): void
    {
        if ($this->isolatedMediaSuiteRoot === null) {
            return;
        }

        try {
            (new Filesystem)->deleteDirectory($this->isolatedMediaSuiteRoot);
        } catch (\Throwable) {
            // Bỏ qua nếu Windows chưa kịp nhả handle file ở bước cleanup test.
        } finally {
            $this->isolatedMediaSuiteRoot = null;
        }
    }
}
