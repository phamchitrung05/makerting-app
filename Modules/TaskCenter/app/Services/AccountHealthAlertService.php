<?php

namespace Modules\TaskCenter\Services;

use Modules\TaskCenter\Models\SystemAlert;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo cảnh báo hệ thống (system_alerts) + bắn Telegram.
 * =====================================================================
 *
 * - raise(): ghi 1 bản ghi alert + (nếu cấu hình) gửi Telegram qua queue.
 * - raiseTokenExpiry / raiseQuota / raiseRenderError: shortcut cho các
 *   loại cảnh báo thường gặp (token MXH hết hạn, lỗi quota, lỗi render).
 * - unread()/markRead(): cho endpoint chuông thông báo Admin.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - raise()               : tạo alert + bắn telegram.
 * - raiseTokenExpiry()    : alert token_expiry.
 * - raiseQuota()          : alert quota.
 * - raiseRenderError()    : alert render.
 * - unread()              : query alert chưa đọc.
 * - markRead()            : đánh dấu đã đọc.
 * INPUT/OUTPUT: điều khiển vòng đời SystemAlert + Telegram.
 * =====================================================================
 */
class AccountHealthAlertService
{
    public function __construct(
        private readonly TelegramNotificationService $telegram,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo một SystemAlert + bắn Telegram nếu cấu hình
     * =====================================================================
     * INPUT: severity, category, title, message, context array.
     * OUTPUT: SystemAlert vừa tạo.
     * SIDE EFFECT:
     * - INSERT system_alerts;
     * - (async) gửi Telegram nếu TelegramNotificationService::isConfigured(),
     *   cập nhật telegram_sent_at.
     * =====================================================================
     */
    public function raise(
        string $severity,
        string $category,
        string $title,
        string $message,
        array $context = [],
    ): SystemAlert {
        $alert = SystemAlert::create([
            'severity' => $severity,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'context_json' => $context ?: null,
        ]);

        // Bắn Telegram (best-effort, không làm hỏng request nếu lỗi).
        if ($this->telegram->isConfigured()) {
            try {
                $sent = $this->telegram->sendAlert($severity, $category, $title, $message, $context);
                if ($sent) {
                    $alert->update(['telegram_sent_at' => now()]);
                }
            } catch (\Throwable $e) {
                // Không để lỗi telegram chặn tạo alert.
                report($e);
            }
        }

        return $alert;
    }

    /**
     * CHỨC NĂNG: Alert token MXH sắp/đã hết hạn (severity warning|critical).
     * INPUT: $accountName, $platform, $expiresAt desc, context.
     * OUTPUT: SystemAlert.
     */
    public function raiseTokenExpiry(string $accountName, string $platform, string $detail, array $context = []): SystemAlert
    {
        return $this->raise(
            SystemAlert::SEV_WARNING,
            'token_expiry',
            "Token {$platform} sắp hết hạn",
            "Tài khoản [{$accountName}] {$detail}. Vui lòng kết nối lại để tránh gián đoạn đăng bài.",
            $context,
        );
    }

    /**
     * CHỨC NĂNG: Alert vượt quota (provider AI, quota MXH...).
     * INPUT: $subject, $detail, context. OUTPUT: SystemAlert.
     */
    public function raiseQuota(string $subject, string $detail, array $context = []): SystemAlert
    {
        return $this->raise(
            SystemAlert::SEV_WARNING,
            'quota',
            'Vượt hạn mức sử dụng',
            "{$subject}: {$detail}",
            $context,
        );
    }

    /**
     * CHỨC NĂNG: Alert lỗi render/video.
     * INPUT: $title, $detail, context. OUTPUT: SystemAlert.
     */
    public function raiseRenderError(string $title, string $detail, array $context = []): SystemAlert
    {
        return $this->raise(
            SystemAlert::SEV_CRITICAL,
            'render',
            $title,
            $detail,
            $context,
        );
    }

    /**
     * CHỨC NĂNG: Query alert chưa đọc (cho chuông thông báo).
     * INPUT: không. OUTPUT: Builder.
     */
    public function unread()
    {
        return SystemAlert::unread()->latest();
    }

    /**
     * CHỨC NĂNG: Đánh dấu đã đọc theo id.
     * INPUT: $id. OUTPUT: bool.
     */
    public function markRead(int $id): bool
    {
        $alert = SystemAlert::find($id);

        return $alert ? $alert->markRead() : false;
    }
}
