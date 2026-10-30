<?php

namespace Modules\TaskCenter\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\TaskCenter\Contracts\TaskTrackerContract;
use Modules\TaskCenter\DTOs\CreateTaskRunDto;
use Modules\TaskCenter\Models\SystemTaskRun;
use Modules\TaskCenter\Registries\TaskRunAdapterRegistry;

class TaskRunController extends Controller
{
    public function __construct(
        private readonly TaskTrackerContract $tracker,
        private readonly TaskRunAdapterRegistry $adapters,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = SystemTaskRun::query()->latest();
        if ($status = $request->query('status')) { $q->where('status', $status); }
        if ($module = $request->query('module')) { $q->where('module', $module); }
        if ($type = $request->query('task_type')) { $q->where('task_type', $type); }
        if ($s = $request->query('search')) { $q->where('title', 'like', '%' . $s . '%'); }

        return BaseResponse::dataTable($q->paginate((int) $request->query('per_page', 15)));
    }

    public function active(): JsonResponse
    {
        $items = SystemTaskRun::active()->latest()->limit(30)->get()
            ->map(fn (SystemTaskRun $t) => $this->serialize($t));

        return BaseResponse::success(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dedupe_key' => ['required', 'string', 'max:191'],
            'module' => ['required', 'string', 'max:50'],
            'task_type' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'channel_group_id' => ['nullable', 'integer'],
            'taskable_type' => ['nullable', 'string', 'max:64'],
            'taskable_id' => ['nullable', 'string', 'max:64'],
            'meta' => ['nullable', 'array'],
        ]);

        $dto = new CreateTaskRunDto(
            dedupeKey: $data['dedupe_key'],
            module: $data['module'],
            taskType: $data['task_type'],
            title: $data['title'],
            channelGroupId: $data['channel_group_id'] ?? null,
            taskableType: $data['taskable_type'] ?? null,
            taskableId: $data['taskable_id'] ?? null,
            initiatedBy: $request->user()?->id,
            meta: $data['meta'] ?? null,
        );

        $task = $this->tracker->create($dto);

        if (! $task->wasRecentlyCreated) {
            return BaseResponse::accepted($this->serialize($task), 'Tác vụ đã được xếp hàng trước đó');
        }

        return BaseResponse::created($this->serialize($task), 'Đã tạo tác vụ');
    }

    public function cancel(string $id): JsonResponse
    {
        $task = $this->tracker->get($id);

        if ($task->isFinished()) {
            return BaseResponse::error('Tác vụ đã kết thúc, không thể hủy', 409);
        }

        $task = $this->tracker->cancel($id);

        return BaseResponse::success($this->serialize($task), 'Đã hủy tác vụ');
    }

    public function adapters(): JsonResponse
    {
        return BaseResponse::success(['adapters' => $this->adapters->all()]);
    }

    private function serialize(SystemTaskRun $t): array
    {
        return [
            'id' => $t->id,
            'module' => $t->module,
            'task_type' => $t->task_type,
            'title' => $t->title,
            'status' => $t->status,
            'progress' => $t->progress,
            'channel_group_id' => $t->channel_group_id,
            'taskable_type' => $t->taskable_type,
            'taskable_id' => $t->taskable_id,
            'meta' => $t->meta_json,
            'error_code' => $t->error_code,
            'error_message' => $t->error_message,
            'started_at' => $t->started_at?->toIso8601String(),
            'completed_at' => $t->completed_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
            'ui' => $this->adapters->meta($t->module, $t->task_type),
        ];
    }
}
