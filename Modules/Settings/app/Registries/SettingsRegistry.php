<?php

namespace Modules\Settings\Registries;

use InvalidArgumentException;
use Modules\Settings\Contracts\ModularSettingsContract;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry trung tâm gom mọi Settings class của các module.
 * =====================================================================
 *
 * Mỗi module gọi ->register(MySettings::class) trong ModuleServiceProvider::boot()
 * để nhóm cài đặt của nó xuất hiện trên trang Admin Settings như một tab mới.
 * Registry kiểm tra class kế thừa Spatie\Settings + implement
 * ModularSettingsContract và khóa group không trùng.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register()        : đăng ký 1 class settings.
 * - has()             : kiểm tra group đã đăng ký chưa.
 * - resolve()         : lấy class theo group (null nếu chưa có).
 * - resolveOrFail()   : lấy class theo group (throw nếu chưa có).
 * - all()             : map group => class.
 * - groups()          : danh sách khóa group.
 * - classes()         : danh sách class.
 * - describe()        : metadata từng group cho API/UI dựng tab.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : class-string của Settings class.
 * - OUTPUT: tra cứu class theo group + metadata.
 * =====================================================================
 */
class SettingsRegistry
{
    /** @var array<string, class-string> map group => settings class */
    private array $classes = [];

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký một Settings class vào registry
     * =====================================================================
     * INPUT:
     * - $class : class-string (Settings + ModularSettingsContract).
     *
     * OUTPUT: void — class được thêm vào $classes[group].
     *
     * SIDE EFFECT: ghi map nội bộ; throw khi class sai hợp đồng.
     *
     * EXCEPTION: InvalidArgumentException khi class không tồn tại, không
     * kế thừa Settings, không implement contract, hoặc group trùng.
     * =====================================================================
     */
    public function register(string $class): void
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Settings class [{$class}] không tồn tại.");
        }

        if (! is_subclass_of($class, Settings::class)) {
            throw new InvalidArgumentException(
                "[{$class}] phải kế thừa Spatie\LaravelSettings\Settings."
            );
        }

        if (! is_subclass_of($class, ModularSettingsContract::class)) {
            throw new InvalidArgumentException(
                "[{$class}] phải implement ModularSettingsContract."
            );
        }

        $group = $class::group();

        if ($group === '') {
            throw new InvalidArgumentException("[{$class}] trả group() rỗng.");
        }

        if (isset($this->classes[$group]) && $this->classes[$group] !== $class) {
            throw new InvalidArgumentException(
                "Group settings [{$group}] đã được [{$this->classes[$group]}] đăng ký, không thể gán lại cho [{$class}]."
            );
        }

        $this->classes[$group] = $class;
    }

    /**
     * CHỨC NĂNG: Kiểm tra một group đã có class đăng ký chưa.
     * INPUT: $group string. OUTPUT: bool. SIDE EFFECT: không.
     */
    public function has(string $group): bool
    {
        return isset($this->classes[$group]);
    }

    /**
     * CHỨC NĂNG: Lấy class theo group, trả null khi chưa đăng ký.
     * INPUT: $group string. OUTPUT: class-string|null. SIDE EFFECT: không.
     */
    public function resolve(string $group): ?string
    {
        return $this->classes[$group] ?? null;
    }

    /**
     * CHỨC NĂNG: Lấy class theo group, throw khi chưa đăng ký.
     * INPUT: $group string.
     * OUTPUT: class-string.
     * EXCEPTION: InvalidArgumentException khi group chưa đăng ký.
     */
    public function resolveOrFail(string $group): string
    {
        return $this->resolve($group)
            ?? throw new InvalidArgumentException("Group settings [{$group}] chưa được đăng ký.");
    }

    /**
     * CHỨC NĂNG: Trả toàn bộ map group => class.
     * INPUT: không. OUTPUT: array<string,class-string>. SIDE EFFECT: không.
     */
    public function all(): array
    {
        return $this->classes;
    }

    /**
     * CHỨC NĂNG: Trả danh sách khóa group đã đăng ký.
     * INPUT: không. OUTPUT: array<int,string>. SIDE EFFECT: không.
     */
    public function groups(): array
    {
        return array_keys($this->classes);
    }

    /**
     * CHỨC NĂNG: Trả danh sách class đã đăng ký.
     * INPUT: không. OUTPUT: array<int,class-string>. SIDE EFFECT: không.
     */
    public function classes(): array
    {
        return array_values($this->classes);
    }

    /**
     * CHỨC NĂNG: Metadata mô tả từng group (label/permission/secrets) cho
     * API trả về để UI dựng tab + biết quyền + biết trường nào là secret.
     * INPUT: không.
     * OUTPUT: array<int,array{group,class,permission,secrets,defaults}>.
     * SIDE EFFECT: không.
     */
    public function describe(): array
    {
        return collect($this->classes)
            ->map(fn (string $class, string $group) => [
                'group' => $group,
                'class' => $class,
                'permission' => $class::permission(),
                'secrets' => $class::secretProperties(),
                'defaults' => $class::defaults(),
            ])
            ->values()
            ->all();
    }
}
