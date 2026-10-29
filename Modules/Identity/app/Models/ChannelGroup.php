<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Identity\Models\User;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model Eloquent cho bảng `channel_groups` (Nhóm kênh/Ngách).
 * =====================================================================
 * Gom cụm tài khoản Fanpage/YouTube/TikTok cùng thương hiệu; `defaults_json`
 * giữ cấu hình mặc định (writing_profile, visual_style, motif, tts_voice,
 * watermark_media_asset, brand_cta_template) để module sản xuất nội dung
 * kế thừa nhất quán.
 * =====================================================================
 */
class ChannelGroup extends Model
{
    protected $table = 'channel_groups';

    protected $fillable = [
        'name',
        'code',
        'color',
        'description',
        'defaults_json',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'defaults_json' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Các user được phân công phụ trách nhóm kênh này.
     * INPUT: không có tham số.
     * OUTPUT: BelongsToMany qua channel_group_user + pivot role_in_group.
     * SIDE EFFECT: không có.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_group_user')
            ->withPivot('role_in_group')
            ->withTimestamps();
    }

    /**
     * Scope chỉ lấy nhóm đang kích hoạt.
     * INPUT: $query Builder. OUTPUT: Builder is_active=true.
     * SIDE EFFECT: không có.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Lấy một giá trị cấu hình mặc định trong defaults_json.
     * INPUT: $key (string), $default.
     * OUTPUT: mixed giá trị cấu hình hoặc $default.
     * SIDE EFFECT: không có.
     */
    public function defaultOf(string $key, mixed $default = null): mixed
    {
        return data_get($this->defaults_json, $key, $default);
    }
}
