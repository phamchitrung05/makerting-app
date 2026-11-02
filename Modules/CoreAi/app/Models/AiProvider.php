<?php

namespace Modules\CoreAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `ai_providers` — cấu hình một nhà cung cấp AI.
 * =====================================================================
 *
 * Giữ thông tin kết nối (base_url, driver, timeout) + khóa API đã mã hóa.
 * KHÔNG cast tự động decrypt — việc giải mã chỉ qua CredentialVault/
 * AiProviderClient khi thực sự gọi HTTP, tránh lộ key vào array/JSON/log.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - models()                    : HasMany AiModel.
 * - scopeActive()               : chỉ provider đang bật.
 * - scopeDriver()               : lọc theo driver.
 * - apiKey()/extraKeys()        : giải mã key chính/phụ (CredentialVault).
 * - allKeys()                   : mảng key [chính, phụ...] plaintext để xoay vòng.
 * - connectionSnapshot()        : mảng cấu hình an toàn ghi vào ai_runs
 *                                  (không chứa key).
 * - markTestResult()            : ghi test_status + last_tested_at.
 * - isOfficial()/isGateway()    : kiểm tra kind.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình provider từ admin (key/base_url/driver + secrets).
 * - OUTPUT: kết nối cho AiProviderClient + danh sách AiModel.
 * =====================================================================
 */
class AiProvider extends Model
{
    protected $table = 'ai_providers';

    public const KIND_OFFICIAL = 'official';
    public const KIND_GATEWAY = 'gateway';
    public const KINDS = [self::KIND_OFFICIAL, self::KIND_GATEWAY];

    public const DRIVER_OPENAI = 'openai';
    public const DRIVER_GEMINI = 'gemini';
    public const DRIVER_ANTHROPIC = 'anthropic';
    public const DRIVER_OPENAI_COMPATIBLE = 'openai-compatible';
    public const DRIVER_ANTHROPIC_COMPATIBLE = 'anthropic-compatible';
    public const DRIVER_ELEVENLABS = 'elevenlabs';
    public const DRIVER_XPIKI = 'xpiki';
    public const DRIVERS = [
        self::DRIVER_OPENAI,
        self::DRIVER_GEMINI,
        self::DRIVER_ANTHROPIC,
        self::DRIVER_OPENAI_COMPATIBLE,
        self::DRIVER_ANTHROPIC_COMPATIBLE,
        self::DRIVER_ELEVENLABS,
        self::DRIVER_XPIKI,
    ];

    public const TEST_OK = 'ok';
    public const TEST_FAILED = 'failed';
    public const TEST_UNTESTED = 'untested';

    protected $fillable = [
        'key',
        'name',
        'kind',
        'driver',
        'base_url',
        'api_key_encrypted',
        'extra_keys_json',
        'config_json',
        'request_timeout',
        'is_active',
        'test_status',
        'last_tested_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'request_timeout' => 'integer',
        'config_json' => 'array',
        'extra_keys_json' => 'array',
        'last_tested_at' => 'datetime',
    ];

    /** Không bao giờ serialize api_key ra JSON/Resource. */
    protected $hidden = [
        'api_key_encrypted',
        'extra_keys_json',
    ];
    /**
     * =====================================================================
     * CHỨC NĂNG: Danh sách model thuộc provider.
     * INPUT: không. OUTPUT: HasMany AiModel.
     * =====================================================================
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'ai_provider_id');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chỉ provider đang bật.
     * INPUT: query. OUTPUT: query đã lọc is_active.
     * =====================================================================
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lọc theo driver.
     * INPUT: query + driver string. OUTPUT: query đã lọc.
     * =====================================================================
     */
    public function scopeDriver($query, string $driver)
    {
        return $query->where('driver', $driver);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giải mã API key chính.
     * =====================================================================
     * INPUT: không. OUTPUT: ?string key plaintext (chỉ dùng nội bộ khi gọi
     *        HTTP; không log/không trả ra ngoài).
     * SIDE EFFECT: gọi CredentialVault::decryptOrNull.
     * =====================================================================
     */
    public function apiKey(): ?string
    {
        return $this->credentialVault()->decryptOrNull($this->api_key_encrypted);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giải mã danh sách key phụ (xoay vòng RPM).
     * =====================================================================
     * INPUT: không. OUTPUT: string[] các key phụ đã giải mã (bỏ entry hỏng).
     * SIDE EFFECT: gọi CredentialVault::decryptOrNull từng entry.
     * =====================================================================
     */
    public function extraKeys(): array
    {
        $ciphers = $this->extra_keys_json;
        if (! is_array($ciphers)) {
            $ciphers = $ciphers === null ? [] : (json_decode((string) $ciphers, true) ?: []);
        }

        $keys = [];
        foreach ($ciphers as $cipher) {
            $plain = $this->credentialVault()->decryptOrNull(is_string($cipher) ? $cipher : null);
            if ($plain !== null && $plain !== '') {
                $keys[] = $plain;
            }
        }

        return $keys;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Toàn bộ key dùng được [chính, phụ...] theo thứ tự ưu tiên.
     * =====================================================================
     * INPUT: không. OUTPUT: string[] key plaintext (rỗng nếu không có key).
     * SIDE EFFECT: giải mã qua CredentialVault.
     * =====================================================================
     */
    public function allKeys(): array
    {
        $keys = [];
        $main = $this->apiKey();
        if ($main !== null && $main !== '') {
            $keys[] = $main;
        }

        return array_merge($keys, $this->extraKeys());
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Snapshot cấu hình kết nối AN TOÀN để ghi vào ai_runs.
     * =====================================================================
     * INPUT: ?AiModel đang dùng. OUTPUT: array — không chứa bất kỳ secret.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function connectionSnapshot(?AiModel $model = null): array
    {
        return [
            'provider_id' => $this->id,
            'provider_key' => $this->key,
            'provider_name' => $this->name,
            'kind' => $this->kind,
            'driver' => $this->driver,
            'base_url' => $this->base_url,
            'request_timeout' => $this->request_timeout,
            'remote_model_id' => $model?->remote_model_id,
            'model_label' => $model?->label,
            'capabilities' => $model?->capabilities(),
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi kết quả test ping mới nhất.
     * =====================================================================
     * INPUT: bool $ok. OUTPUT: void.
     * SIDE EFFECT: UPDATE test_status + last_tested_at.
     * =====================================================================
     */
    public function markTestResult(bool $ok): void
    {
        $this->forceFill([
            'test_status' => $ok ? self::TEST_OK : self::TEST_FAILED,
            'last_tested_at' => now(),
        ])->save();
    }

    /** INPUT: không. OUTPUT: bool kind = 'official'. */
    public function isOfficial(): bool
    {
        return $this->kind === self::KIND_OFFICIAL;
    }

    /** INPUT: không. OUTPUT: bool kind = 'gateway'. */
    public function isGateway(): bool
    {
        return $this->kind === self::KIND_GATEWAY;
    }

    /**
     * Resolve CredentialVault từ container (tránh hard dependency vòng
     * Core->CoreAi ở mức property).
     * INPUT: không. OUTPUT: CredentialVault.
     */
    protected function credentialVault(): \Modules\Core\Services\CredentialVault
    {
        return app(\Modules\Core\Services\CredentialVault::class);
    }
}