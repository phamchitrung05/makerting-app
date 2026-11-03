<?php

namespace Modules\WritingStyle\TaskCenter;

use Modules\TaskCenter\Contracts\TaskRunAdapterContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Adapter hiển thị task giải mã văn phong trên popup.
 * =====================================================================
 * Đăng ký vào TaskRunAdapterRegistry trong WritingStyleServiceProvider::boot
 * để <SystemTaskQueuePopup> hiện nhãn "Giải mã văn phong" + icon + route.
 * =====================================================================
 */
class WritingProfileAnalyzeAdapter implements TaskRunAdapterContract
{
    public function module(): string
    {
        return 'writingstyle';
    }

    public function taskType(): string
    {
        return 'writing_profile.analyze';
    }

    public function label(): string
    {
        return 'Giải mã văn phong';
    }

    public function icon(): string
    {
        return 'tabler-dna';
    }

    public function detailRoute(): ?string
    {
        return 'admin-writing-style-writing-profiles';
    }
}
