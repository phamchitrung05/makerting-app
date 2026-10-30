<?php

namespace Modules\TaskCenter\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\TaskCenter\Models\SystemAlert;
use Modules\TaskCenter\Services\AccountHealthAlertService;

class SystemAlertController extends Controller
{
    public function __construct(private readonly AccountHealthAlertService $alerts) {}

    public function index(Request $request): JsonResponse
    {
        $q = SystemAlert::query()->latest();
        if ($sev = $request->query('severity')) { $q->where('severity', $sev); }
        if ($cat = $request->query('category')) { $q->where('category', $cat); }
        if ($request->boolean('unread')) { $q->unread(); }

        return BaseResponse::dataTable($q->paginate((int) $request->query('per_page', 15)));
    }

    public function unreadCount(): JsonResponse
    {
        return BaseResponse::success([
            'count' => SystemAlert::unread()->count(),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $ok = $this->alerts->markRead($id);

        return $ok
            ? BaseResponse::success(['id' => $id], 'Đã đánh dấu đã đọc')
            : BaseResponse::error('Cảnh báo không tồn tại', 404);
    }

    public function markAllRead(): JsonResponse
    {
        SystemAlert::unread()->update(['read_at' => now()]);

        return BaseResponse::success([], 'Đã đánh dấu tất cả đã đọc');
    }
}
