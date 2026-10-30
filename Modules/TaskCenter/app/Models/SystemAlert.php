<?php

namespace Modules\TaskCenter\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model SystemAlert — cảnh báo hệ thống.
 * =====================================================================
 * severity: info|warning|critical; read_at đánh dấu đã đọc;
 * telegram_sent_at đánh dấu đã bắn Telegram.
 * =====================================================================
 */
class SystemAlert extends Model
{
    protected $table = 'system_alerts';

    public const SEV_INFO = 'info';
    public const SEV_WARNING = 'warning';
    public const SEV_CRITICAL = 'critical';

    protected $fillable = [
        'severity', 'category', 'title', 'message',
        'context_json', 'telegram_sent_at', 'read_at',
    ];

    protected $casts = [
        'context_json' => 'array',
        'telegram_sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * CHỨC NĂNG: Scope alert chưa đọc.
     * INPUT: query. OUTPUT: Builder.
     */
    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    /**
     * CHỨC NĂNG: Đánh dấu đã đọc (ghi read_at=now).
     * INPUT: không. OUTPUT: bool.
     * SIDE EFFECT: UPDATE read_at.
     */
    public function markRead(): bool
    {
        if ($this->read_at) { return true; }

        return $this->update(['read_at' => now()]);
    }
}
