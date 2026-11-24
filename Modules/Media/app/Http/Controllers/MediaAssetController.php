<?php

namespace Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
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
use Modules\Media\Support\MediaMetadataHelper;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
 * - GET    /api/v1/admin/media/assets/{asset}/stream        stream file vật lý
 * - GET    /api/v1/admin/media/assets/{asset}/share-link    tạo signed URL chia sẻ tạm (60 phút)
 * - GET    /api/v1/admin/media/assets/{asset}/share-stream  stream chia sẻ qua signed URL
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
    private const SHARE_LINK_TTL_MINUTES = 60;

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
            ->with([
                'creator',
                'channelGroup',
                'media' => fn ($mq) => $mq->where('collection_name', MediaAsset::COLLECTION_LIBRARY),
            ])
            ->withCount('usages')
            /* Nhóm Thùng rác: chỉ asset đã soft-delete — phải khai báo trước
             * các filter khác để global scope SoftDeletes không lọc trước. */
            ->when(
                filter_var($request->query('trashed'), FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->onlyTrashed()
            )
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->filled('visibility'), fn ($q) => $q->where('visibility', $request->query('visibility')))
            ->when($request->filled('folder'), fn ($q) => $this->applyFolderFilter($q, (string) $request->query('folder')))
            ->when($request->filled('channel_group_id'), fn ($q) => $q->where('channel_group_id', $request->query('channel_group_id')))
            /* Bộ lọc nhanh cột trái:
             * - recent : 30 ngày gần nhất.
             * - shared : đang được gán vào model nào đó (usages > 0).
             * - ai     : có metadata ai_generation trong file media. */
            ->when($request->query('quick') === 'recent', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
            ->when($request->query('quick') === 'shared', fn ($q) => $q->has('usages'))
            ->when(
                $request->query('quick') === 'ai',
                fn ($q) => $q->whereHas(
                    'media',
                    fn ($mq) => $mq
                        ->where('collection_name', MediaAsset::COLLECTION_LIBRARY)
                        ->whereNotNull('custom_properties->ai_generation')
                )
            )
            ->when($request->filled('search'), fn ($q) => $q->where(function ($qq) use ($request) {
                $s = (string) $request->query('search');
                $qq->where('title', 'like', "%{$s}%")
                   ->orWhere('alt_text', 'like', "%{$s}%")
                   ->orWhere('description', 'like', "%{$s}%")
                   ->orWhereRaw('LOWER(CAST(tags AS CHAR)) LIKE ?', ['%' . mb_strtolower($s, 'UTF-8') . '%']);
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
            ->with(['media' => fn ($mq) => $mq->where('collection_name', MediaAsset::COLLECTION_LIBRARY)])
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->filled('folder'), fn ($q) => $this->applyFolderFilter($q, (string) $request->query('folder')))
            ->when($request->filled('channel_group_id'), fn ($q) => $q->where('channel_group_id', $request->query('channel_group_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%' . $request->query('search') . '%'))
            ->orderByDesc('id')
            ->limit(100);

        $options = $query->get()->map(function (MediaAsset $a): array {
            // Private: không lộ URL trực tiếp — frontend dùng /assets/{id}/stream.
            $media = $a->visibility === MediaAsset::VISIBILITY_PUBLIC ? $a->mediaFile() : null;
            $fullUrl = $media?->getFullUrl();

            return [
                'id' => $a->id,
                'title' => $a->title,
                'kind' => $a->kind,
                'folder' => $a->folder,
                'url' => $fullUrl,
                'thumb' => $media
                    ? ($a->isImage() ? ($media->getUrl('thumb') ?: $fullUrl) : $fullUrl)
                    : null,
            ];
        })->values();

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
        /* Mỗi folder kèm số asset (tự bổ sung prefix cha còn thiếu + cộng dồn
         * count từ thư mục con để cây thư mục FE không bị rơi nút con thành gốc). */
        $rows = MediaAsset::query()
            ->select('folder')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('folder')
            ->get();

        $countsByPath = [];

        foreach ($rows as $row) {
            $rawPath = trim((string) $row->folder);
            if ($rawPath === '') {
                continue;
            }

            $segments = array_values(array_filter(
                explode('/', $rawPath),
                static fn (string $segment): bool => $segment !== '',
            ));

            if ($segments === []) {
                continue;
            }

            $rowCount = (int) $row->count;
            $prefix = '';
            foreach ($segments as $segment) {
                $prefix = $prefix === '' ? $segment : "{$prefix}/{$segment}";
                $countsByPath[$prefix] = ($countsByPath[$prefix] ?? 0) + $rowCount;
            }
        }

        ksort($countsByPath, SORT_STRING);

        $folders = [];
        foreach ($countsByPath as $name => $count) {
            $folders[] = [
                'name' => $name,
                'count' => $count,
            ];
        }

        return BaseResponse::success($folders);
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/stats
     * =====================================================================
     * Thống kê tổng số tệp & dung lượng toàn thư viện (kể cả thùng rác).
     * INPUT: không. OUTPUT: BaseResponse::success {total_files,total_size_bytes,trashed_files,trashed_size_bytes}.
     * =====================================================================
     */
    public function stats()
    {
        $morphClass = (new MediaAsset)->getMorphClass();

        $activeRow = DB::table('media_assets')
            ->leftJoin('media', function ($join) use ($morphClass): void {
                $join->on('media.model_id', '=', 'media_assets.id')
                    ->where('media.model_type', '=', $morphClass)
                    ->where('media.collection_name', '=', MediaAsset::COLLECTION_LIBRARY);
            })
            ->whereNull('media_assets.deleted_at')
            ->selectRaw('COUNT(DISTINCT media_assets.id) as total_files, COALESCE(SUM(media.size), 0) as total_size_bytes')
            ->first();

        $trashedRow = DB::table('media_assets')
            ->leftJoin('media', function ($join) use ($morphClass): void {
                $join->on('media.model_id', '=', 'media_assets.id')
                    ->where('media.model_type', '=', $morphClass)
                    ->where('media.collection_name', '=', MediaAsset::COLLECTION_LIBRARY);
            })
            ->whereNotNull('media_assets.deleted_at')
            ->selectRaw('COUNT(DISTINCT media_assets.id) as trashed_files, COALESCE(SUM(media.size), 0) as trashed_size_bytes')
            ->first();

        return BaseResponse::success([
            'total_files' => (int) ($activeRow->total_files ?? 0),
            'total_size_bytes' => (int) ($activeRow->total_size_bytes ?? 0),
            'trashed_files' => (int) ($trashedRow->trashed_files ?? 0),
            'trashed_size_bytes' => (int) ($trashedRow->trashed_size_bytes ?? 0),
        ]);
    }

    /**
     * =====================================================================
     * POST /api/v1/admin/media/assets/bulk-folder
     * =====================================================================
     * Chuyển hàng loạt asset sang một thư mục mới.
     * INPUT: ids (int[]), folder (string). OUTPUT: BaseResponse::success {updated, folder}.
     * =====================================================================
     */
    public function bulkMoveFolder(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', Rule::exists('media_assets', 'id')->whereNull('deleted_at')],
            'folder' => MediaMetadataHelper::folderRules(['required']),
        ]);

        $folder = MediaMetadataHelper::normalizeFolder($validated['folder']);
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        $updated = MediaAsset::query()
            ->whereIn('id', $ids)
            ->update(['folder' => $folder]);

        return BaseResponse::success(
            ['updated' => (int) $updated, 'folder' => $folder],
            "Đã chuyển {$updated} tệp sang thư mục \"{$folder}\"",
        );
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/{asset}
     * =====================================================================
     * INPUT: MediaAsset, Request (?trashed=1 cho phép xem chi tiết trong thùng rác).
     * OUTPUT: BaseResponse::success chi tiết + usages.
     * =====================================================================
     */
    public function show(MediaAsset $asset, Request $request)
    {
        $this->abortIfTrashedWithoutAccess($asset, $request);

        $this->loadAssetDetails($asset, withUsages: true);

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
            'folder' => MediaMetadataHelper::normalizeFolder($request->input('folder', 'root')),
            'visibility' => $request->input('visibility', MediaAsset::VISIBILITY_PUBLIC),
            'channel_group_id' => $request->input('channel_group_id'),
            'title' => $request->input('title'),
            'alt_text' => $request->input('alt_text'),
            'description' => $request->input('description'),
            'tags' => $request->input('tags'),
            'allowed_kinds' => $request->input('allowed_kinds'),
            'dedupe' => $request->boolean('dedupe', true),
            'created_by' => $request->user()?->id,
        ];

        // Multi-upload khi có 'files[]'.
        if ($request->hasFile('files')) {
            try {
                $assets = $this->upload->executeMany($request->file('files'), $options);
            } catch (InvalidArgumentException | FileIsTooBig $e) {
                return BaseResponse::error($e->getMessage(), 422);
            }

            $assetCollection = $this->loadAssetDetails(new EloquentCollection($assets));

            return BaseResponse::success(
                MediaAssetResource::collection($assetCollection),
                'Tải lên ' . count($assets) . ' file thành công',
            );
        }

        try {
            $asset = $this->upload->execute($request->file('file'), $options);
        } catch (InvalidArgumentException | FileIsTooBig $e) {
            return BaseResponse::error($e->getMessage(), 422);
        }

        $statusCode = $asset->wasRecentlyCreated ? 201 : 200;
        $message = $asset->wasRecentlyCreated
            ? 'Tải lên thành công'
            : 'File đã tồn tại (trả về bản ghi cũ theo checksum)';

        $this->loadAssetDetails($asset);

        return BaseResponse::success(
            new MediaAssetResource($asset),
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
        $data = $request->validated();
        $oldVisibility = $asset->visibility;
        $newVisibility = $data['visibility'] ?? $oldVisibility;

        // Đổi visibility -> di chuyển file vật lý giữa media_public <-> media_private
        // trước khi lưu DB; nếu chuyển sang private thất bại thì trả 502 để giữ
        // nhất quán (không đánh dấu private trong DB trong khi file vẫn nằm public).
        if ($newVisibility !== $oldVisibility) {
            $moved = $asset->moveMediaFileToDisk(MediaAsset::diskForVisibility($newVisibility));
            if (! $moved && $newVisibility === MediaAsset::VISIBILITY_PRIVATE) {
                return BaseResponse::error('Không chuyển được file sang disk riêng tư.', 502);
            }
        }

        $asset->update($data);

        $fresh = $this->loadAssetDetails($asset->fresh());

        return BaseResponse::success(
            new MediaAssetResource($fresh),
            'Cập nhật media thành công',
        );
    }

    /**
     * =====================================================================
     * DELETE /api/v1/admin/media/assets/{asset}
     * =====================================================================
     * INPUT: MediaAsset. OUTPUT: BaseResponse::success (gỡ usages + soft-delete).
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
     * POST /api/v1/admin/media/assets/{asset}/restore
     * =====================================================================
     * Khôi phục asset đã xóa mềm từ thùng rác.
     * INPUT: MediaAsset (withTrashed). OUTPUT: BaseResponse::success MediaAssetResource.
     * =====================================================================
     */
    public function restore(MediaAsset $asset)
    {
        if ($asset->trashed()) {
            $asset->restore();
        }

        $fresh = $this->loadAssetDetails($asset->fresh());

        return BaseResponse::success(
            new MediaAssetResource($fresh),
            'Đã khôi phục media',
        );
    }

    /**
     * =====================================================================
     * DELETE /api/v1/admin/media/assets/{asset}/force
     * =====================================================================
     * Xóa vĩnh viễn asset khỏi DB và xóa toàn bộ file vật lý/conversions Spatie.
     * INPUT: MediaAsset (withTrashed). OUTPUT: BaseResponse::success {id, force_deleted}.
     * =====================================================================
     */
    public function forceDestroy(MediaAsset $asset)
    {
        $id = $asset->id;

        DB::transaction(function () use ($asset): void {
            $asset->usages()->delete();
            // Spatie InteractsWithMedia::bootInteractsWithMedia() xóa file vật lý khi forceDeleting = true.
            $asset->forceDelete();
        });

        return BaseResponse::success(
            ['id' => $id, 'force_deleted' => true],
            'Đã xóa vĩnh viễn media',
        );
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/{asset}/stream
     * =====================================================================
     * Stream file vật lý của asset (dùng cho video private/preview).
     * INPUT: MediaAsset, Request (?download=1 để đính Content-Disposition; ?trashed=1 cho thùng rác).
     * OUTPUT: StreamedResponse (Content-Type theo mime file Spatie).
     * =====================================================================
     */
    public function stream(MediaAsset $asset, Request $request)
    {
        $this->abortIfTrashedWithoutAccess($asset, $request);

        $media = $asset->mediaFile();
        if (! $media) {
            return BaseResponse::error('Asset chưa có file vật lý.', 404);
        }

        return $this->streamMediaFile($media, $request);
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/{asset}/share-link
     * =====================================================================
     * Cấp relative signed URL tạm thời (TTL 60 phút) để chia sẻ asset private.
     * INPUT: MediaAsset.
     * OUTPUT: BaseResponse::success {url, expires_at, ttl_minutes}.
     * =====================================================================
     */
    public function shareLink(MediaAsset $asset)
    {
        if (! $asset->mediaFile()) {
            return BaseResponse::error('Asset chưa có file vật lý.', 404);
        }

        $expiresAt = now()->addMinutes(self::SHARE_LINK_TTL_MINUTES);
        $signedUrl = URL::temporarySignedRoute(
            'api.media.assets.share-stream',
            $expiresAt,
            ['asset' => $asset->id],
            absolute: false,
        );

        return BaseResponse::success([
            'url' => $signedUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'ttl_minutes' => self::SHARE_LINK_TTL_MINUTES,
        ]);
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/assets/{asset}/share-stream
     * =====================================================================
     * Phát stream file vật lý qua relative signed URL (không yêu cầu Bearer token).
     * INPUT: MediaAsset, Request.
     * OUTPUT: StreamedResponse | Response | JsonResponse.
     * =====================================================================
     */
    public function shareStream(MediaAsset $asset, Request $request)
    {
        $media = $asset->mediaFile();
        if (! $media) {
            return BaseResponse::error('Asset chưa có file vật lý.', 404);
        }

        return $this->streamMediaFile($media, $request);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Stream file vật lý Spatie có hỗ trợ HTTP Range (206 / 416)
     * =====================================================================
     * Dùng chung cho stream() và shareStream(). Đọc kích thước thực từ disk,
     * stream theo chunk 64 KB và hỗ trợ tua video/audio via Range header.
     * INPUT: Media $media, Request $request.
     * OUTPUT: StreamedResponse | Response | JsonResponse.
     * =====================================================================
     */
    protected function streamMediaFile(Media $media, Request $request)
    {
        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            return BaseResponse::error(
                "File vật lý của asset này không còn trên disk ({$disk}).",
                404,
            );
        }

        $fileSize = (int) $filesystem->size($path);
        $download = $request->boolean('download');

        // Content-Type: ép octet-stream cho SVG/HTML (chặn inline JS execute).
        $mime = $media->mime_type ?: 'application/octet-stream';
        $safeMime = in_array($mime, ['image/svg+xml', 'text/html', 'application/xhtml+xml'], true)
            ? 'application/octet-stream'
            : $mime;

        $baseHeaders = [
            'Content-Type' => $safeMime,
            'Content-Disposition' => ($download ? 'attachment' : 'inline') .
                '; filename="' . addslashes($media->file_name) . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'",
        ];

        $rangeHeader = $request->header('Range');
        $start = 0;
        $end = max(0, $fileSize - 1);
        $status = 200;

        if (is_string($rangeHeader) && trim($rangeHeader) !== '') {
            $trimmedRange = trim($rangeHeader);

            // Chỉ xử lý đơn vị bytes=; các multi-range có dấu phẩy giữ nguyên 200 theo RFC 9110.
            if (str_starts_with(strtolower($trimmedRange), 'bytes=') && ! str_contains($trimmedRange, ',')) {
                $parsed = $this->parseByteRange($trimmedRange, $fileSize);
                if ($parsed === null) {
                    return response('', 416, array_merge($baseHeaders, [
                        'Content-Range' => "bytes */{$fileSize}",
                    ]));
                }

                [$start, $end] = $parsed;
                $status = 206;
            }
        }

        $stream = $filesystem->readStream($path);
        if ($stream === false || $stream === null || ! is_resource($stream)) {
            return BaseResponse::error(
                "File vật lý của asset này không còn trên disk ({$disk}).",
                404,
            );
        }

        $length = $fileSize === 0 ? 0 : ($end - $start + 1);
        $headers = array_merge($baseHeaders, [
            'Content-Length' => (string) $length,
        ]);

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        return new StreamedResponse(function () use ($stream, $start, $length): void {
            $chunkSize = 65536;

            if ($start > 0) {
                $meta = stream_get_meta_data($stream);
                if (! empty($meta['seekable'])) {
                    fseek($stream, $start);
                } else {
                    $toSkip = $start;
                    while ($toSkip > 0 && ! feof($stream)) {
                        $skipped = fread($stream, min($chunkSize, $toSkip));
                        if ($skipped === false || $skipped === '') {
                            break;
                        }
                        $toSkip -= strlen($skipped);
                    }
                }
            }

            $remaining = $length;
            while ($remaining > 0 && ! feof($stream)) {
                $buffer = fread($stream, min($chunkSize, $remaining));
                if ($buffer === false || $buffer === '') {
                    break;
                }
                echo $buffer;
                $remaining -= strlen($buffer);
            }

            fclose($stream);
        }, $status, $headers);
    }

    /**
     * CHỨC NĂNG: Parse header `Range: bytes=start-end` đơn phần; trả null khi không hợp lệ / ngoài tầm.
     * INPUT: string $rangeHeader, int $fileSize.
     * OUTPUT: ?array{0:int, 1:int} [start, end] hoặc null (-> 416).
     */
    private function parseByteRange(string $rangeHeader, int $fileSize): ?array
    {
        if ($fileSize <= 0) {
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/i', $rangeHeader, $matches)) {
            return null;
        }

        $startRaw = $matches[1];
        $endRaw = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return null;
        }

        if ($startRaw === '') {
            $suffixLength = (int) $endRaw;
            if ($suffixLength <= 0) {
                return null;
            }

            $start = max(0, $fileSize - $suffixLength);

            return [$start, $fileSize - 1];
        }

        $start = (int) $startRaw;
        if ($start >= $fileSize) {
            return null;
        }

        $end = $endRaw !== '' ? min((int) $endRaw, $fileSize - 1) : ($fileSize - 1);
        if ($end < $start) {
            return null;
        }

        return [$start, $end];
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
     * Chặn truy cập show/stream đối với asset đã soft-delete khi caller không truyền ?trashed=1.
     */
    private function abortIfTrashedWithoutAccess(MediaAsset $asset, Request $request): void
    {
        if ($asset->trashed() && ! filter_var($request->query('trashed'), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }
    }

    /**
     * Eager load đồng nhất các quan hệ cho MediaAssetResource (tránh N+1 và lặp code).
     *
     * @template TModel of MediaAsset|EloquentCollection
     *
     * @param  TModel  $target
     * @return TModel
     */
    private function loadAssetDetails(MediaAsset|EloquentCollection $target, bool $withUsages = false): MediaAsset|EloquentCollection
    {
        $relations = [
            'creator',
            'channelGroup',
            'media' => fn ($mq) => $mq->where('collection_name', MediaAsset::COLLECTION_LIBRARY),
        ];

        if ($withUsages) {
            $relations['usages.mediaAsset.media'] = fn ($mq) => $mq->where('collection_name', MediaAsset::COLLECTION_LIBRARY);
        }

        return $target->load($relations)->loadCount('usages');
    }

    /**
     * CHỨC NĂNG: Lọc query theo tên folder hoặc toàn bộ thư mục con dưới prefix đó.
     * INPUT: $query, string $folder.
     */
    private function applyFolderFilter($query, string $folder): void
    {
        $normalized = trim($folder, '/ ');
        if ($normalized === '') {
            return;
        }

        $escaped = addcslashes($normalized, '\\%_');
        $query->where(function ($fq) use ($normalized, $escaped): void {
            $fq->where('folder', $normalized)
                ->orWhere('folder', 'like', $escaped . '/%');
        });
    }

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
