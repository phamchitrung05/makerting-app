<?php

namespace Modules\TaskCenter\Contracts;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng adapter mà mỗi module đăng ký để ánh xạ
 * task_type -> metadata hiển thị + icon + route chi tiết trên popup.
 * =====================================================================
 *
 * Mỗi module gọi TaskRunAdapterRegistry::register() trong boot() với
 * adapter implement contract này để popup biết cách hiển thị loại task.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - module()        : tên module sở hữu (vd 'CoreAi').
 * - taskType()      : loại task (vd 'video_render').
 * - label()         : nhãn tiếng Việt hiển thị.
 * - icon()          : icon tabler trên popup.
 * - detailRoute()   : route name đi tới trang chi tiết (nullable).
 * INPUT/OUTPUT: metadata mô tả một loại task cho UI.
 * =====================================================================
 */
interface TaskRunAdapterContract
{
    /** @return string tên module, vd 'CoreAi', 'VideoStudio' */
    public function module(): string;

    /** @return string loại task, vd 'video_render', 'ai_analysis' */
    public function taskType(): string;

    /** @return string nhãn hiển thị, vd 'Dựng video FFmpeg' */
    public function label(): string;

    /** @return string icon tabler, vd 'tabler-movie' */
    public function icon(): string;

    /** @return string|null route name chi tiết, vd 'admin-video-studio-projects-show' */
    public function detailRoute(): ?string;
}
