<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model Eloquent cho bảng `oauth_client_apps`.
 * =====================================================================
 *
 * Lưu cụm OAuth App (Google/Meta/TikTok) để xoay vòng và chia quota.
 * client_secret được mã hóa qua CredentialVault trước khi ghi; model này chỉ
 * giữ bản mã hóa (`client_secret_encrypted`), không tự giải mã.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - scopeActive($query): lọc app đang bật
 * - scopeForPlatform($query, $platform): lọc theo nền tảng
 * - hasQuotaRemaining(): còn quota ngày không
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : platform, client_id/secret, redirect_uri, scopes_json, quota fields
 * - OUTPUT: bản ghi OAuthClientApp + helpers quota
 * =====================================================================
 */
class OAuthClientApp extends Model
{
    protected $table = 'oauth_client_apps';

    protected $fillable = [
        'platform',
        'name',
        'client_id',
        'client_secret_encrypted',
        'redirect_uri',
        'scopes_json',
        'daily_quota_limit',
        'daily_quota_used',
        'quota_reset_at',
        'is_active',
    ];

    protected $casts = [
        'scopes_json' => 'array',
        'is_active' => 'boolean',
        'daily_quota_limit' => 'integer',
        'daily_quota_used' => 'integer',
        'quota_reset_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret_encrypted',
    ];

    /**
     * CHỨC NĂNG: scope lọc app đang kích hoạt.
     * INPUT: $query Builder. OUTPUT: Builder đã lọc is_active=true.
     * SIDE EFFECT: không có.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * CHỨC NĂNG: scope lọc theo nền tảng.
     * INPUT: $query Builder, $platform ('facebook'|'youtube'|'tiktok').
     * OUTPUT: Builder đã lọc platform.
     * SIDE EFFECT: không có.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * CHỨC NĂNG: kiểm tra app còn quota ngày để dùng không.
     * INPUT: không có tham số. OUTPUT: bool.
     * SIDE EFFECT: không có.
     */
    public function hasQuotaRemaining(): bool
    {
        if ($this->daily_quota_limit === null) {
            return true;
        }

        return $this->daily_quota_used < $this->daily_quota_limit;
    }
}
