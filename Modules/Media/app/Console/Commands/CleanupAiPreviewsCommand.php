<?php

namespace Modules\Media\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Media\Services\AiImagePreviewStore;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Dọn các file tạm AI (ai-previews & ai-references) quá TTL.
 * =====================================================================
 *
 * File nhị phân preview sinh bởi GenerateAiPreviewImagesAction nằm trên disk
 * `temp_processing`. Khi hết `media.ai_preview_ttl_minutes`, Cache hết hạn nhưng
 * file vật lý vẫn còn trên ổ đĩa; command này dọn định kỳ các file đã quá hạn.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Option `--ttl` (phút, tùy chọn — mặc định đọc config `media.ai_preview_ttl_minutes`).
 * - OUTPUT: Exit code Command::SUCCESS + thống kê số file và dung lượng đã dọn.
 * =====================================================================
 */
class CleanupAiPreviewsCommand extends Command
{
    protected $signature = 'media:cleanup-ai-previews
        {--ttl= : Số phút giữ file tạm trước khi xóa (mặc định theo config media.ai_preview_ttl_minutes)}';

    protected $description = 'Dọn các file ảnh preview và ảnh tham chiếu AI tạm đã quá hạn TTL';

    /**
     * CHỨC NĂNG: Quét và xóa các file tạm AI cũ hơn ngưỡng TTL cấu hình.
     * INPUT: AiImagePreviewStore $store. OUTPUT: int (self::SUCCESS).
     * SIDE EFFECT: Xóa file quá hạn trên disk `temp_processing` và ghi Log::info khi có file xóa.
     */
    public function handle(AiImagePreviewStore $store): int
    {
        $ttlOption = $this->option('ttl');
        $ttlMinutes = is_numeric($ttlOption) && (int) $ttlOption > 0
            ? (int) $ttlOption
            : null;

        $summary = $store->cleanupExpiredTempFiles($ttlMinutes);

        if ($summary['deleted'] > 0) {
            Log::info('Cleaned up expired Media AI temp files', [
                'deleted' => $summary['deleted'],
                'bytes_freed' => $summary['bytes_freed'],
                'ttl_minutes' => $summary['ttl_minutes'],
            ]);
        }

        $this->info(sprintf(
            'Đã dọn %d file AI tạm (giải phóng %d bytes, TTL %d phút).',
            $summary['deleted'],
            $summary['bytes_freed'],
            $summary['ttl_minutes'],
        ));

        return self::SUCCESS;
    }
}
