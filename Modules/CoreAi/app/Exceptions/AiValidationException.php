<?php

namespace Modules\CoreAi\Exceptions;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Exception khi output AI không khớp JSON Schema.
 * =====================================================================
 *
 * Ném bởi `JsonSchemaValidator`/`CoreAiGateway` khi response của model
 * không parse được JSON hoặc vi phạm schema (thiếu required, sai type,
 * vượt enum...). `errors` là danh sách lỗi phẳng để log + retry prompt.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - withErrors()   : tạo exception kèm danh sách lỗi chi tiết.
 * - errors()       : lấy mảng lỗi [{path, message, expected, actual}].
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : message + mảng errors.
 * - OUTPUT: AiGatewayException con (errorCode='validation').
 * =====================================================================
 */
class AiValidationException extends AiGatewayException
{
    /**
     * CHỨC NĂNG: khởi tạo exception với danh sách lỗi validation.
     * INPUT: message, errors, ?previous. OUTPUT: instance.
     */
    public function __construct(
        string $message = 'AI output không khớp JSON Schema',
        protected array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'validation', ['errors' => $errors], $previous);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo exception từ mảng lỗi validator trả về.
     * INPUT: errors + ?message. OUTPUT: self.
     * =====================================================================
     */
    public static function withErrors(array $errors, ?string $message = null): self
    {
        return new self($message ?? 'AI output không khớp JSON Schema', $errors);
    }

    /** INPUT: không. OUTPUT: array lỗi chi tiết. */
    public function errors(): array
    {
        return $this->errors;
    }
}