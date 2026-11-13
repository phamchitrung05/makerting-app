<?php

namespace Modules\VisualStyle\TaskCenter;

use Modules\TaskCenter\Contracts\TaskRunAdapterContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Adapter hiển thị task giải mã DNA ảnh trên popup.
 * =====================================================================
 * Đăng ký vào TaskRunAdapterRegistry trong VisualStyleServiceProvider::boot
 * để <SystemTaskQueuePopup> hiện nhãn "Giải mã DNA ảnh" + icon + route.
 * =====================================================================
 */
class VisualStyleAnalyzeAdapter implements TaskRunAdapterContract
{
    public function module(): string
    {
        return 'visualstyle';
    }

    public function taskType(): string
    {
        return 'visual_style.analyze';
    }

    public function label(): string
    {
        return 'Giải mã DNA ảnh';
    }

    public function icon(): string
    {
        return 'tabler-photo-spark';
    }

    public function detailRoute(): ?string
    {
        return 'admin-visual-style-visual-styles-list';
    }
}
