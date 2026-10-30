<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Settings\Registries\SettingsRegistry;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Điều phối đọc/ghi cấu hình module qua Spatie Settings,
 * khóa phiên bản (optimistic lock -> HTTP 409) và che trường bí mật.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - describe()         : liệt kê group kèm metadata.
 * - get()              : đọc group -> mảng {values(đã che), version, meta}.
 * - update()           : ghi group với khóa version.
 * - maskSecrets()      : biến secret thành {name}_configured cờ bool.
 * - buildWritePayload(): tách secret rỗng ra khỏi payload ghi.
 * - load()             : nạp/cache instance Settings của group.
 * - ensureRegistered() : thêm class vào config('settings.settings').
 * - currentVersion()   : đọc version trong transaction.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : group string, payload array, expected_version int.
 * - OUTPUT: mảng payload đã mask / JsonResponse 409.
 * =====================================================================
 */
class ModularSettingsService
{
    /**
     * Cache instance Settings trong phạm vi 1 HTTP request để tránh
     * resolve DB lặp lại khi vừa get() vừa update() cùng group.
     *
     * @var array<string, Settings>
     */
    private array $requestCache = [];

    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * CHỨC NĂNG: Metadata tất cả group settings đã đăng ký.
     * INPUT: không. OUTPUT: array<int,array>. SIDE EFFECT: không.
     */
    public function describe(): array
    {
        return $this->registry->describe();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đọc một nhóm cài đặt, che secret, kèm version hiện tại
     * =====================================================================
     * INPUT: $group string.
     * OUTPUT: array{group,version,values,secrets} — values đã mask secret.
     * SIDE EFFECT: ghi requestCache[group].
     * EXCEPTION: InvalidArgumentException nếu group chưa đăng ký.
     * =====================================================================
     */
    public function get(string $group): array
    {
        $settings = $this->load($group);
        $values = $settings->toArray();
        $secrets = $this->contractOf($group)::secretProperties();

        return [
            'group' => $group,
            'version' => (int) ($values['version'] ?? 1),
            'values' => $this->maskSecrets($values, $secrets),
            'secrets' => $secrets,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cập nhật một nhóm cài đặt với khóa phiên bản lạc quan
     * =====================================================================
     * INPUT:
     * - $group           : khóa nhóm.
     * - $payload         : map property=>giá trị mới (đã qua FormRequest).
     * - $expectedVersion : version client tin là mới nhất.
     *
     * OUTPUT: array payload sau update (đã mask) + version mới,
     *         hoặc JsonResponse 409 khi version lệch.
     *
     * SIDE EFFECT: DB transaction + lockForUpdate; fill+save upsert payload.
     * TRANSACTION: toàn bộ đọc->so version->ghi trong 1 transaction.
     * EXCEPTION: InvalidArgumentException khi group chưa đăng ký.
     * =====================================================================
     */
    public function update(string $group, array $payload, int $expectedVersion)
    {
        $secrets = $this->contractOf($group)::secretProperties();

        [$writePayload] = $this->buildWritePayload($payload, $secrets);

        return DB::transaction(function () use ($group, $writePayload, $expectedVersion, $secrets) {
            $current = $this->currentVersion($group);

            if ($current !== $expectedVersion) {
                return BaseResponse::error(
                    'Cấu hình đã bị người khác sửa trước. Tải lại trang để lấy phiên bản mới.',
                    409,
                    ['version' => $current],
                );
            }

            /** @var Settings $settings */
            $settings = $this->load($group, fresh: true);

            $settings->version = $current + 1;

            if (! empty($writePayload)) {
                $settings->fill($writePayload);
            }

            $settings->save();

            unset($this->requestCache[$group]);

            $values = $settings->toArray();

            return [
                'group' => $group,
                'version' => (int) ($values['version'] ?? ($current + 1)),
                'values' => $this->maskSecrets($values, $secrets),
                'secrets' => $secrets,
            ];
        });
    }

    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Nạp (hoặc lấy cache) instance Settings của group.
     * INPUT: $group string; $fresh bool (bỏ cache request).
     * OUTPUT: Settings instance đã load từ DB.
     * SIDE EFFECT: đăng ký class vào config settings + ghi requestCache.
     */
    private function load(string $group, bool $fresh = false): Settings
    {
        if (! $fresh && isset($this->requestCache[$group])) {
            return $this->requestCache[$group];
        }

        $class = $this->ensureRegistered($group);

        /** @var Settings $settings */
        $settings = app($class);

        return $this->requestCache[$group] = $settings;
    }

    /**
     * CHỨC NĂNG: Đảm bảo class của group được Spatie biết để `new $class()`
     * load đúng repository — thêm vào config('settings.settings') nếu thiếu.
     * INPUT: $group string. OUTPUT: class-string. SIDE EFFECT: merge config.
     */
    private function ensureRegistered(string $group): string
    {
        $class = $this->registry->resolveOrFail($group);

        $registered = config('settings.settings', []);
        if (! in_array($class, $registered, true)) {
            config(['settings.settings' => array_merge($registered, [$class])]);
        }

        return $class;
    }

    /**
     * CHỨC NĂNG: Lấy contract class của group (đã resolveOrFail).
     * INPUT: $group string. OUTPUT: class-string<ModularSettingsContract>.
     */
    private function contractOf(string $group): string
    {
        return $this->registry->resolveOrFail($group);
    }

    /**
     * CHỨC NĂNG: Đọc version hiện tại của group thẳng từ bảng settings
     * trong transaction (lockForUpdate); mặc định 1 khi chưa có row.
     * INPUT: $group string. OUTPUT: int.
     * SIDE EFFECT: SELECT ... FOR UPDATE (trong transaction của update()).
     */
    private function currentVersion(string $group): int
    {
        // Khóa tất cả row của group để chặn update đồng thời chen vào.
        SettingsProperty::query()
            ->where('group', $group)
            ->lockForUpdate()
            ->get(['name']);

        $row = SettingsProperty::query()
            ->where('group', $group)
            ->where('name', 'version')
            ->first(['payload']);

        if ($row === null) {
            return 1; // Chưa seed -> version khởi đầu.
        }

        $decoded = json_decode($row->payload, true);

        return (int) (is_numeric($decoded) ? $decoded : ($decoded['value'] ?? 1));
    }

    /**
     * CHỨC NĂNG: Thay từng secret bằng cờ '{name}_configured' => bool và
     * xóa key giá trị thật khỏi payload trả ra.
     * INPUT: $values array, $secrets array<int,string>.
     * OUTPUT: array payload đã che. SIDE EFFECT: không.
     */
    private function maskSecrets(array $values, array $secrets): array
    {
        foreach ($secrets as $name) {
            $values[$name . '_configured'] = filled($values[$name] ?? null);
            unset($values[$name]);
        }

        return $values;
    }

    /**
     * CHỨC NĂNG: Tách ra payload thực sự ghi — loại key version + key cờ
     * '{name}_configured' + bỏ secret khi client gửi rỗng/null (giữ cũ).
     * INPUT: $payload array, $secrets array<int,string>.
     * OUTPUT: [writePayload, secretsTouched] — secretsTouched cho log.
     * SIDE EFFECT: không.
     */
    private function buildWritePayload(array $payload, array $secrets): array
    {
        unset($payload['version']);

        $secretsTouched = [];

        foreach ($secrets as $name) {
            unset($payload[$name . '_configured']);

            if (array_key_exists($name, $payload)) {
                $raw = $payload[$name];
                if ($raw === null || $raw === '') {
                    unset($payload[$name]); // Giữ giá trị cũ.
                } else {
                    $secretsTouched[] = $name;
                }
            }
        }

        return [$payload, $secretsTouched];
    }
}
