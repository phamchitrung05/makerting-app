<?php

namespace Modules\CoreAi\Exceptions;

use RuntimeException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Exception lỗi từ cổng AI (HTTP/provider/timeout...).
 * =====================================================================
 *
 * Ném khi driver không gọi được provider hoặc provider trả lỗi không
 * xử lý được. `errorCode` phân loại (network|auth|rate_limit|invalid_*
 * |provider_error|timeout|unsupported) để gateway map sang
 * ai_runs.error_code + caller quyết định retry/fallback.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - withCode()        : tạo exception kèm errorCode + context.
 * - network()         : lỗi kết nối/DNS/timeout.
 * - auth()            : key sai/hết hạn/không đủ quyền.
 * - rateLimit()       : chạm giới hạn RPM/quota.
 * - providerError()   : provider trả 4xx/5xx khác.
 * - validation()      : dữ liệu đầu ra không khớp schema.
 * - unsupported()     : provider/model không hỗ trợ capability.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : message + errorCode + context (provider/model/status).
 * - OUTPUT: RuntimeException với errorCode + context để log/diagnostics.
 * =====================================================================
 */
class AiGatewayException extends RuntimeException
{
    /**
     * CHỨC NĂNG: khởi tạo exception kèm errorCode + context.
     * INPUT: message, errorCode, context, ?previous, code.
     * OUTPUT: instance.
     */
    public function __construct(
        string $message = '',
        protected string $errorCode = 'provider_error',
        protected array $context = [],
        ?\Throwable $previous = null,
        int $code = 0,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** INPUT: không. OUTPUT: string mã lỗi. */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** INPUT: không. OUTPUT: array ngữ cảnh (provider/model/status...). */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo exception tùy ý với code + context.
     * INPUT: code, message, context. OUTPUT: self.
     * =====================================================================
     */
    public static function withCode(string $code, string $message, array $context = []): self
    {
        return new self($message, $code, $context);
    }

    /** INPUT: message + context. OUTPUT: self — errorCode='network'. */
    public static function network(string $message, array $context = []): self
    {
        return new self($message, 'network', $context);
    }

    /** INPUT: message + context. OUTPUT: self — errorCode='auth'. */
    public static function auth(string $message, array $context = []): self
    {
        return new self($message, 'auth', $context);
    }

    /** INPUT: message + context. OUTPUT: self — errorCode='rate_limit'. */
    public static function rateLimit(string $message, array $context = []): self
    {
        return new self($message, 'rate_limit', $context);
    }

    /** INPUT: message + context. OUTPUT: self — errorCode='provider_error'. */
    public static function providerError(string $message, array $context = []): self
    {
        return new self($message, 'provider_error', $context);
    }

    /** INPUT: message + context. OUTPUT: self — errorCode='validation'. */
    public static function validation(string $message, array $context = []): self
    {
        return new self($message, 'validation', $context);
    }

    /** INPUT: message + context. OUTPUT: self — errorCode='unsupported'. */
    public static function unsupported(string $message, array $context = []): self
    {
        return new self($message, 'unsupported', $context);
    }
}