<?php

namespace Modules\TaskCenter\Registries;

use InvalidArgumentException;
use Modules\TaskCenter\Contracts\TaskRunAdapterContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry adapter task_type -> metadata hiển thị popup.
 * =====================================================================
 * Module khác đăng ký adapter trong ServiceProvider::boot() để popup
 * SystemTaskQueuePopup biết icon/label/route cho từng loại task.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register() : đăng ký adapter (module+taskType unique).
 * - resolve()  : lấy adapter theo module+taskType.
 * - meta()     : trả metadata {module,taskType,label,icon,detailRoute}.
 * - all()      : map key->metadata.
 * INPUT/OUTPUT: tra cứu adapter theo 'module:taskType'.
 * =====================================================================
 */
class TaskRunAdapterRegistry
{
    /** @var array<string, TaskRunAdapterContract> map 'module:taskType' => adapter */
    private array $adapters = [];

    /**
     * CHỨC NĂNG: Đăng ký một adapter.
     * INPUT: TaskRunAdapterContract. OUTPUT: void.
     * SIDE EFFECT: ghi map; throw nếu trùng module+taskType.
     * EXCEPTION: InvalidArgumentException khi trùng khóa.
     */
    public function register(TaskRunAdapterContract $adapter): void
    {
        $key = $adapter->module() . ':' . $adapter->taskType();

        if (isset($this->adapters[$key])) {
            throw new InvalidArgumentException(
                "Adapter task [{$key}] đã được đăng ký."
            );
        }

        $this->adapters[$key] = $adapter;
    }

    /**
     * CHỨC NĂNG: Lấy adapter theo module+taskType (null nếu chưa có).
     * INPUT: $module, $taskType. OUTPUT: TaskRunAdapterContract|null.
     */
    public function resolve(string $module, string $taskType): ?TaskRunAdapterContract
    {
        return $this->adapters[$module . ':' . $taskType] ?? null;
    }

    /**
     * CHỨC NĂNG: Metadata hiển thị của một task (fallback nếu chưa đăng ký).
     * INPUT: $module, $taskType.
     * OUTPUT: array{module,taskType,label,icon,detailRoute}.
     */
    public function meta(string $module, string $taskType): array
    {
        $a = $this->resolve($module, $taskType);

        return [
            'module' => $module,
            'taskType' => $taskType,
            'label' => $a?->label() ?? $taskType,
            'icon' => $a?->icon() ?? 'tabler-player-play',
            'detailRoute' => $a?->detailRoute(),
        ];
    }

    /**
     * CHỨC NĂNG: Toàn bộ metadata đã đăng ký.
     * INPUT: không. OUTPUT: array<string,array>.
     */
    public function all(): array
    {
        return collect($this->adapters)
            ->map(fn ($a) => $this->meta($a->module(), $a->taskType()))
            ->all();
    }
}
