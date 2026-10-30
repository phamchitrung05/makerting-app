<?php

namespace Modules\TaskCenter\Services;

use Illuminate\Support\Facades\Log;
use Modules\Core\Services\SafeRemoteFetcher;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Gửi cảnh báo hệ thống qua Telegram Bot API.
 * =====================================================================
 *
 * - Dùng SafeRemoteFetcher (chống SSRF + DNS pin) để POST tới api.telegram.org.
 * - Token/chat_id lấy từ config('taskcenter.telegram') (env), không lưu DB.
 * - Gọi bất đồng bộ qua queue 'default' — không chặn HTTP request.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - send()        : gửi 1 tin nhắn text.
 * - sendAlert()   : gửi cảnh báo có severity/title/message.
 * - isConfigured(): kiểm tra có token+chat_id chưa.
 * INPUT/OUTPUT: gọi Telegram Bot API, trả bool thành công.
 * =====================================================================
 */
class TelegramNotificationService
{
    public function __construct(private readonly SafeRemoteFetcher $fetcher) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra cấu hình Telegram đã đủ chưa
     * =====================================================================
     * INPUT: không. OUTPUT: bool — true khi có token + chat_id.
     * SIDE EFFECT: đọc config.
     * =====================================================================
     */
    public function isConfigured(): bool
    {
        return filled($this->botToken()) && filled($this->chatId());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gửi một tin nhắn text tới Telegram
     * =====================================================================
     * INPUT: $text string (hỗ trợ HTML cơ bản), $chatId override (nullable).
     * OUTPUT: bool — true khi Telegram trả ok.
     *
     * SIDE EFFECT: HTTPS POST tới api.telegram.org qua SafeRemoteFetcher;
     * không ném exception — log lỗi và trả false khi thất bại.
     * =====================================================================
     */
    public function send(string $text, ?string $chatId = null): bool
    {
        if (! $this->isConfigured()) {
            return false; // Chưa cấu hình -> bỏ qua im lặng.
        }

        $token = $this->botToken();
        $target = $chatId ?? $this->chatId();

        $result = $this->fetcher->fetch(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'method' => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'chat_id' => $target,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]),
            ],
        );

        $ok = $result->isSuccess();

        if (! $ok) {
            Log::warning('TelegramNotificationService: gửi thất bại', [
                'status' => $result->status,
            ]);
        }

        return $ok;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gửi cảnh báo có định dạng severity/title/message/context
     * =====================================================================
     * INPUT: severity, category, title, message, context array.
     * OUTPUT: bool.
     * =====================================================================
     */
    public function sendAlert(string $severity, string $category, string $title, string $message, array $context = []): bool
    {
        $icon = match ($severity) {
            'critical' => '🔴',
            'warning' => '🟡',
            default => '🔵',
        };

        $lines = [
            "{$icon} <b>{$title}</b>",
            "Hạng mục: <code>{$category}</code>",
            '',
            $message,
        ];

        if (! empty($context)) {
            $lines[] = '';
            $lines[] = '<code>' . e(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</code>';
        }

        return $this->send(implode("\n", $lines));
    }

    /* ======================== helpers ======================== */

    private function botToken(): ?string
    {
        return config('taskcenter.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
    }

    private function chatId(): ?string
    {
        return config('taskcenter.telegram.chat_id') ?: env('TELEGRAM_CHAT_ID');
    }
}
