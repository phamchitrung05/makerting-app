<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model Eloquent cho bảng `idempotency_keys`.
 * =====================================================================
 *
 * Bản ghi khóa idempotency: lưu request_hash, response đã render và mốc
 * locked_until/expires_at. Middleware dùng để trả lại response cũ hoặc
 * báo 409 khi request trùng key đang được xử lý.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - isLocked(): key còn đang bị khóa (đang xử lý) không
 * - isCompleted(): đã lưu response hoàn tất chưa
 * - isExpired(): đã hết hạn không
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : key, request_hash, response_status/body_json, locked_until, expires_at
 * - OUTPUT: bản ghi IdempotencyKey + helpers trạng thái
 * =====================================================================
 */
class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'user_id',
        'route_signature',
        'request_hash',
        'response_status',
        'response_body_json',
        'locked_until',
        'expires_at',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'response_body_json' => 'array',
        'locked_until' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * CHỨC NĂNG: kiểm tra key còn đang bị khóa (request đang xử lý) không.
     * INPUT: không có tham số. OUTPUT: bool — true khi locked_until ở tương lai.
     * SIDE EFFECT: không có.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * CHỨC NĂNG: kiểm tra request đã hoàn tất và đã lưu response chưa.
     * INPUT: không có tham số. OUTPUT: bool — true khi có response_status.
     * SIDE EFFECT: không có.
     */
    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * CHỨC NĂNG: kiểm tra key đã hết hạn không.
     * INPUT: không có tham số. OUTPUT: bool — true khi expires_at ở quá khứ.
     * SIDE EFFECT: không có.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
