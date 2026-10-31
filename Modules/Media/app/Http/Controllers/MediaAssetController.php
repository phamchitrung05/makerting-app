<?php

namespace Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Media\Actions\UploadMediaAssetAction;
use Modules\Media\Contracts\MediaUsageServiceContract;
use Modules\Media\Http\Requests\MediaAssetIndexRequest;
use Modules\Media\Http\Requests\MediaAssetStoreRequest;
use Modules\Media\Http\Requests\MediaAssetUpdateRequest;
use Modules\Media\Http\Requests\MediaUsageAttachRequest;
use Modules\Media\Http\Requests\MediaUsageDetachRequest;
use Modules\Media\Http\Resources\MediaAssetResource;
use Modules\Media\Http\Resources\MediaAssetUsageResource;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Models\MediaAssetUsage;
use Modules\Media\Registries\MediaSlotRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller API quản lý MediaAsset + usages theo slot.
 * =====================================================================
 *
 * Endpoint:
 * - GET    /api/v1/admin/media/assets            index (dataTable + lọc)
 * - GET    /api/v1/admin/media/assets/options    dropdown nhẹ
 * - GET    /api/v1/admin/media/assets/folders    danh sách folder hiện có
 * - GET    /api/v1/admin/media/assets/{asset}    show chi tiết + usages
 * - POST   /api/v1/admin/media/assets            upload file mới
 * - PUT    /api/v1/admin/media/assets/{asset}    update metadata
 * - DELETE /api/v1/admin/media/assets/{asset}    soft delete
 * - GET    /api/v1/admin/media/assets/{asset}/stream   stream file vật lý
 * - GET    /api/v1/admin/media/slots             metadata slot registry
 * - POST   /api/v1/admin/media/usages            attach/sync media vào model
 * - GET    /api/v1/admin/media/usages            list usages của 1 model+slot
 * - DELETE /api/v1/admin/media/usages            detach media khỏi model
 *
 * Quyền: media.view cho đọc, media.manage cho ghi.
 * =====================================================================
 */
class MediaAssetController extends Controller
{
    public function __construct(
        private readonly UploadMediaAssetAction $upload,
        private readonly MediaUsageServiceContract $usageService,
        private readonly MediaSlotRegistry $slots,
    ) {}

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets
     * =====================================================================
     * INPUT: MediaAssetIndexRequest (kind/visibility/folder/channel_group_id/
     *        search/itemsPerPage/page/sortBy/orderBy).
     * OUTPUT: BaseResponse::dataTable items=MediaAssetResource.
     * =====================================================================
     */
    public function index(MediaAssetIndexRequest $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 24));
        $perPage = max(1, min(200, $perPage));

        $query = MediaAsset::query()
            ->with(['creator', 'channelGroup'])
            ->withCount('usages')
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->filled('visibility'), fn ($q) => $q->where('visibility', $request->query('visibility')))
            ->when($request->filled('folder'), fn ($q) => $q->where('folder', $request->query('folder')))
            ->when($request->filled('channel_group_id'), fn ($q) => $q->where('channel_group_id', $request->query('channel_group_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($qq) use ($request) {
                $s = (string) $request->query('search');
                $qq->where('title', 'like', "%{$s}%")
                   ->orWhere('alt_text', 'like', "%{$s}%");
            }));

        // Sort: whitelist cột được phép
        $sortable = ['id', 'title', 'kind', 'visibility', 'folder', 'created_at', 'updated_at'];
        $sortBy = in_array($request->query('sortBy'), $sortable, true)
            ? $request->query('sortBy')
            : 'id';
        $order = $request->query('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $paginator = $query->paginate($perPage)->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                MediaAssetResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/options
     * =====================================================================
     * INPUT: Request (kind, folder, channel_group_id, search).
     * OUTPUT: BaseResponse::success mảng {id,title,kind,folder,url}.
     * =====================================================================
     */
    public function options(Request $request)
    {
        $query = MediaAsset::query()
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->filled('folder'), fn ($q) => $q->where('folder', $request->query('folder')))
            ->when($request->filled('channel_group_id'), fn ($q) => $q->where('channel_group_id', $request->query('channel_group_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%' . $request->query('search') . '%'))
            ->orderByDesc('id')
            ->limit(100);

        $options = $query->get()->map(fn (MediaAsset $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'kind' => $a->kind,
            'folder' => $a->folder,
            'url' => $a->mediaFile()?->getFullUrl(),
            'thumb' => $a->mediaFile()?->getUrl('thumb') ?: $a->mediaFile()?->getFullUrl(),
        ])->values();

        return BaseResponse::success($options);
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/folders
     * =====================================================================
     * INPUT: không. OUTPUT: BaseResponse::success mảng folder distinct.
     * =====================================================================
     */
    public function folders()
    {
        $folders = MediaAsset::query()
            ->select('folder')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder')
            ->values();

        return BaseResponse::success($folders);
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/{asset}
     * =====================================================================
     * INPUT: MediaAsset. OUTPUT: BaseResponse::success chi tiết + usages.
     * =====================================================================
     */
    public function show(MediaAsset $asset)
    {
        $asset->load(['creator', 'channelGroup', 'usages.mediaAsset'])
            ->loadCount('usages');

        return BaseResponse::success(new MediaAssetResource($asset));
    }

    /**
     * =====================================================================
     * POST /api/v1/admin/media/assets
     * =====================================================================
     * INPUT: MediaAssetStoreRequest (file|files[] + metadata).
     * OUTPUT: BaseResponse::created MediaAssetResource (single) hoặc
     *         BaseResponse::success mảng (multi).
     * =====================================================================
     */
    public function store(MediaAssetStoreRequest $request)
    {
        $options = [
            'folder' => $request->input('folder', 'root'),
            'visibility' => $request->input('visibility', MediaAsset::VISIBILITY_PUBLIC),
            'channel_group_id' => $request->input('channel_group_id'),
            'title' => $request->input('title'),
            'alt_text' => $request->input('alt_text'),
            'allowed_kinds' => $request->input('allowed_kinds'),
            'dedupe' => $request->boolean('dedupe', true),
            'created_by' => $request->user()?->id,
        ];

        // Multi-upload khi có 'files[]'.
        if ($request->hasFile('files')) {
            try {
                $assets = $this->upload->executeMany($request->file('files'), $options);
            } catch (InvalidArgumentException $e) {
                return BaseResponse::error($e->getMessage(), 422);
            }

            return BaseResponse::success(
                MediaAssetResource::collection(collect($assets)),
                'Tải lên ' . count($assets) . ' file thành công',
            );
        }

        try {
            $asset = $this->upload->execute($request->file('file'), $options);
        } catch (InvalidArgumentException $e) {
            return BaseResponse::error($e->getMessage(), 422);
        }

        $statusCode = $asset->wasRecentlyCreated ? 201 : 200;
        $message = $asset->wasRecentlyCreated
            ? 'Tải lên thành công'
            : 'File đã tồn tại (trả về bản ghi cũ theo checksum)';

        return BaseResponse::success(
            new MediaAssetResource($asset->load(['creator', 'channelGroup'])->loadCount('usages')),
            $message,
        )->setStatusCode($statusCode);
    }

    /**
     * =====================================================================
     * PUT /api/v1/admin/media/assets/{asset}
     * =====================================================================
     * INPUT: MediaAsset, MediaAssetUpdateRequest.
     * OUTPUT: BaseResponse::success MediaAssetResource.
     * =====================================================================
     */
    public function update(MediaAsset $asset, MediaAssetUpdateRequest $request)
    {
        $asset->update($request->validated());

        return BaseResponse::success(
            new MediaAssetResource($asset->fresh(['creator', 'channelGroup'])->loadCount('usages')),
            'Cập nhật media thành công',
        );
    }

    /**
     * =====================================================================
     * DELETE /api/v1/admin/media/assets/{asset}
     * =====================================================================
     * INPUT: MediaAsset. OUTPUT: BaseResponse::success (204 khi không còn usage).
     * =====================================================================
     */
    public function destroy(MediaAsset $asset)
    {
        // Soft delete không kích FK cascade -> phải dọn usages thủ công,
        // nếu không bảng usages trỏ asset đã xóa mềm (model->hasThumbnail()
        // trả true nhưng ->thumbnail() trả null).
        $usages = $asset->usages()->count();

        DB::transaction(function () use ($asset) {
            // Gỡ toàn bộ usage trước khi soft delete — bản ghi usages
            // không tồn tại khi caller query -> hasThumbnail() trả false đúng.
            $asset->usages()->delete();
            $asset->delete();
        });

        return BaseResponse::success(
            ['id' => $asset->id, 'usages_detached' => $usages],
            'Đã xóa media' . ($usages > 0 ? " (đã gỡ {$usages} liên kết)" : ''),
        );
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/{asset}/stream
     * =====================================================================
     * Stream file vật lý của asset (dùng cho video private/preview).
     * INPUT: MediaAsset, Request (?download=1 để đính Content-Disposition).
     * OUTPUT: StreamedResponse (Content-Type theo mime file Spatie).
     * =====================================================================
     */
    public function stream(MediaAsset $asset, Request $request)
    {
        $media = $asset->mediaFile();
        if (! $media) {
            return BaseResponse::error('Asset chưa có file vật lý.', 404);
        }

        // Asset private -> stream qua controller có auth; đảm bảo file vật lý
        // nằm trên media_private (không được nằm public nếu cờ lệch).
        $disk = $media->disk;
        if ($asset->visibility === MediaAsset::VISIBILITY_PRIVATE && $disk !== 'media_private') {
            // Nếu file vẫn nằm public (data cũ): vẫn stream nhưng kèm header
            // X-Content-Type-Options + CSP để hạn chế inline execute.
        }

        $path = $media->getPathRelativeToRoot();
        $download = $request->boolean('download');

        // Content-Type: ép octet-stream cho SVG/private (chặn inline JS execute
        // khi ai đó vẫn gửi được file svg vào private disk).
        $mime = $media->mime_type ?: 'application/octet-stream';
        $safeMime = in_array($mime, ['image/svg+xml', 'text/html', 'application/xhtml+xml'], true)
            ? 'application/octet-stream'
            : $mime;

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = Storage::disk($disk)->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $safeMime,
            'Content-Length' => (string) $media->size,
            'Content-Disposition' => ($download ? 'attachment' : 'inline') .
                '; filename="' . addslashes($media->file_name) . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'",
        ]);
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/slots
     * =====================================================================
     * Metadata các slot đã đăng ký — dùng cho UI biết kind/maxItems/visibility.
     * INPUT: không. OUTPUT: BaseResponse::success MediaSlotRegistry.describe().
     * =====================================================================
     */
    public function slots()
    {
        return BaseResponse::success($this->slots->describe());
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/usages
     * =====================================================================
     * List usages của 1 model+slot (theo sort_order).
     * INPUT: Request (linkable_type, linkable_id, slot).
     * OUTPUT: BaseResponse::success MediaAssetUsageResource[].
     * =====================================================================
     */
    public function usages(Request $request)
    {
        $validated = $request->validate([
            'linkable_type' => ['required', 'string', 'max:64'],
            'linkable_id' => ['required', 'integer'],
            'slot' => ['required', 'string', 'max:80'],
        ]);

        $model = $this->resolveModel($validated['linkable_type'], (int) $validated['linkable_id']);
        if (! $model) {
            return BaseResponse::error('Model đích không tồn tại.', 404);
        }

        // Dùng morphClass từ model thay vì input linkable_type thô — tránh
        // lệch kiểu (alias 'post' vs FQCN) giữa lúc ghi và lúc đọc.
        $usages = MediaAssetUsage::query()
            ->with('mediaAsset')
            ->where('linkable_type', $model->getMorphClass())
            ->where('linkable_id', $model->getKey())
            ->where('slot', $validated['slot'])
            ->orderBy('sort_order')
            ->get();

        return BaseResponse::success(MediaAssetUsageResource::collection($usages));
    }

    /**
     * =====================================================================
     * POST /api/v1/admin/media/usages
     * =====================================================================
     * Gán/sync media vào model theo slot.
     * INPUT: MediaUsageAttachRequest (linkable_type/linkable_id/slot/ids[]/mode).
     * OUTPUT: BaseResponse::success MediaAssetUsageResource[].
     * =====================================================================
     */
    public function attach(MediaUsageAttachRequest $request)
    {
        $data = $request->validated();
        $model = $this->resolveModel($data['linkable_type'], (int) $data['linkable_id']);
        if (! $model) {
            return BaseResponse::error('Model đích không tồn tại.', 404);
        }

        $mode = $data['mode'] ?? 'attach';
        $ids = array_map('intval', $data['media_asset_ids']);

        try {
            $usages = match ($mode) {
                'sync' => $this->usageService->sync($model, $ids, $data['slot']),
                'replace' => $this->usageService->attachMany($model, $ids, $data['slot'], true),
                default => $this->usageService->attachMany($model, $ids, $data['slot'], false),
            };
        } catch (InvalidArgumentException $e) {
            return BaseResponse::error($e->getMessage(), 422);
        }

        // Eager load mediaAsset cho từng usage (Support Collection -> Eloquent Collection).
        $eloquent = new \Illuminate\Database\Eloquent\Collection($usages->all());
        $eloquent->load('mediaAsset');

        return BaseResponse::success(
            MediaAssetUsageResource::collection($eloquent),
            'Đã gán media vào slot ' . $data['slot'],
        );
    }

    /**
     * =====================================================================
     * DELETE /api/v1/admin/media/usages
     * =====================================================================
     * Gỡ media khỏi slot của model.
     * INPUT: MediaUsageDetachRequest.
     * OUTPUT: BaseResponse::success { detached: int }.
     * =====================================================================
     */
    public function detach(MediaUsageDetachRequest $request)
    {
        $data = $request->validated();
        $model = $this->resolveModel($data['linkable_type'], (int) $data['linkable_id']);
        if (! $model) {
            return BaseResponse::error('Model đích không tồn tại.', 404);
        }

        $detached = 0;
        if (! empty($data['detach_all'])) {
            $detached = $this->usageService->detachAll($model, $data['slot']);
        } else {
            foreach ($data['media_asset_ids'] ?? [] as $id) {
                if ($this->usageService->detach($model, (int) $id, $data['slot'])) {
                    $detached++;
                }
            }
        }

        return BaseResponse::success(
            ['detached' => $detached],
            'Đã gỡ ' . $detached . ' media khỏi slot ' . $data['slot'],
        );
    }

    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Nạp model đích từ morph_type alias/FQCN + id
     * =====================================================================
     * Bảo mật: CHỈ chấp nhận model có trait `HasMediaSlots` (hợp đồng gán
     * media theo slot). Model tùy ý (User/OAuthClientApp/Role...) kế thừa
     * Eloquent nhưng không dùng trait sẽ trả null -> controller trả 422.
     *
     * INPUT: $type (alias morphMap hoặc FQCN), $id.
     * OUTPUT: ?Model — null khi class không tồn tại / không phải Model /
     *         không dùng HasMediaSlots / không tìm thấy bản ghi.
     * SIDE EFFECT: SELECT model đích.
     * =====================================================================
     */
    private function resolveModel(string $type, int $id): ?Model
    {
        // Ưu tiên morphMap alias; fallback FQCN.
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        // Chỉ cho phép model đã khai báo trait HasMediaSlots —
        // chặn gán media vào model tùy ý (User, Role, OAuthClientApp...).
        if (! in_array(\Modules\Media\Traits\HasMediaSlots::class, class_uses_recursive($class), true)) {
            return null;
        }

        return $class::find($id);
    }
}
