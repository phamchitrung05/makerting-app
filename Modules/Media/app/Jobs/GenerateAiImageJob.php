<?php

namespace Modules\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Media\Actions\GenerateAiPreviewImagesAction;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Job nền gọi CoreAi tạo bộ ảnh preview tạm cho dialog Media.
 * =====================================================================
 *
 * Chạy trên queue cấu hình tại `media.ai_image_queue` (mặc định `'default'`).
 * Ủy quyền toàn bộ việc gọi CoreAi, lưu disk tạm và cập nhật Cache tracker
 * cho GenerateAiPreviewImagesAction.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : jobId (UUID), userId, payload yêu cầu tạo ảnh.
 * - OUTPUT: trạng thái `ready` hoặc `failed` lưu vào Cache cho endpoint polling.
 * =====================================================================
 */
class GenerateAiImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    /**
     * CHỨC NĂNG: Khởi tạo job và gắn queue cấu hình từ `media.ai_image_queue`.
     * INPUT: string $jobId, int $userId, array $payload.
     * OUTPUT: instance job sẵn sàng dispatch.
     */
    public function __construct(
        public string $jobId,
        public int $userId,
        public array $payload,
    ) {
        $this->onQueue((string) config('media.ai_image_queue', 'default'));
    }

    /**
     * CHỨC NĂNG: Xử lý tạo ảnh AI ở worker nền và cập nhật tracker Cache.
     * INPUT: GenerateAiPreviewImagesAction $action (Laravel container inject).
     * OUTPUT: void.
     * SIDE EFFECT: gọi CoreAiGatewayContract, ghi disk `temp_processing` + Cache.
     */
    public function handle(GenerateAiPreviewImagesAction $action): void
    {
        $action->execute($this->jobId, $this->userId, $this->payload);
    }
}
