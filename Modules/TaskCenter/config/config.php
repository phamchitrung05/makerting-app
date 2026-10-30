<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình module TaskCenter.
 * =====================================================================
 * - telegram.bot_token / chat_id : cấu hình gửi cảnh báo Telegram.
 * - poll_interval_ms             : chu kỳ popup polling active tasks.
 * =====================================================================
 */
return [
    'name' => 'TaskCenter',

    // Cấu hình Telegram Bot cho cảnh báo hệ thống.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    // Chu kỳ popup polling active tasks (ms) trên giao diện Admin.
    'poll_interval_ms' => (int) env('TASK_CENTER_POLL_MS', 5000),
];
