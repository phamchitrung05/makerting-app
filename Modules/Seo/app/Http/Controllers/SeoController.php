<?php

namespace Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Seo\Actions\SyncSeoMetadataAction;
use Modules\Seo\Http\Resources\SeoMetadataResource;
use Modules\Seo\Registries\SeoModelRegistry;
use Modules\Seo\Services\SeoMetadataService;
use Modules\Seo\Traits\HasSeoMeta;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller điều phối HTTP của module Seo.
 * =====================================================================
 *
 * Endpoint phục vụ khối <SeoMetaCard /> khi lưu/đọc meta SEO của một
 * Domain Model bất kỳ qua morph (seoable_type + seoable_id). Vì meta SEO
 * là quan hệ 1-1, không cần CRUD nhiều bản ghi — chỉ GET (đọc/resolved) và
 * PUT (upsert ghi đè). Controller chỉ điều phối HTTP -> Action/Service.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - resolve()   : GET  — meta thô + meta đã fallback cho preview.
 * - update()    : PUT  — ghi đè các trường seo_* / og_*.
 * - findModel() : resolve model chủ từ seoable_type/seoable_id (morphMap).
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request {seoable_type, seoable_id} + payload seo_*.
 * - OUTPUT: BaseResponse::success (SeoMetadataResource + resolved meta).
 * =====================================================================
 */
class SeoController extends Controller
{
    public function __construct(
        private readonly SeoMetadataService $service,
        private readonly SyncSeoMetadataAction $sync,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: GET meta SEO của model — gồm bản ghi thô + meta đã fallback.
     * =====================================================================
     * INPUT: Request query {seoable_type, seoable_id}.
     * OUTPUT: BaseResponse::success { meta: ?SeoMetadataResource, resolved: array }.
     * =====================================================================
     */
    public function resolve(Request $request)
    {
        $model = $this->findModel($request);
        if (! $model) {
            return BaseResponse::error('Không tìm thấy đối tượng cần lấy SEO.', 404);
        }

        return BaseResponse::success([
            'meta' => $model->seo() ? new SeoMetadataResource($model->seo()) : null,
            'resolved' => $this->service->resolve($model),
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: PUT ghi đè meta SEO của model (upsert theo morph).
     * =====================================================================
     * INPUT: Request {seoable_type, seoable_id} + các trường seo_* / og_*.
     * OUTPUT: BaseResponse::success SeoMetadataResource + resolved meta.
     * =====================================================================
     */
    public function update(Request $request)
    {
        $model = $this->findModel($request);
        if (! $model) {
            return BaseResponse::error('Không tìm thấy đối tượng cần cập nhật SEO.', 404);
        }

        $payload = $request->validate([
            'focus_keyword' => ['nullable', 'string', 'max:160'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            // Chỉ cho phép http/https — chặn ftp/s3/file/data... làm canonical.
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots_index' => ['sometimes', 'boolean'],
            'robots_follow' => ['sometimes', 'boolean'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            // Chỉ cho phép MediaAsset ảnh + công khai + chưa xóa.
            'og_image_id' => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('media_assets', 'id')
                    ->where(fn ($q) => $q
                        ->where('kind', \Modules\Media\Models\MediaAsset::KIND_IMAGE)
                        ->where('visibility', \Modules\Media\Models\MediaAsset::VISIBILITY_PUBLIC)
                        ->whereNull('deleted_at')),
            ],
            'schema_org_json' => ['nullable', 'array'],
        ]);

        $meta = $this->sync->execute($model, $payload);

        return BaseResponse::success([
            'meta' => new SeoMetadataResource($meta),
            'resolved' => $this->service->resolve($model->fresh()),
        ], 'Đã lưu siêu dữ liệu SEO.');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve model chủ từ morph alias + id trong request.
     * =====================================================================
     * Dùng Relation::getMorphedModel() để map 'post' => Post::class (hỗ trợ
     * morphMap của enforceMorphMap); fallback coi type là FQCN.
     *
     * AN TOÀN: chỉ cho phép model thuộc whitelist SEO + thực sự dùng
     * HasSeoMeta — chặn cả IDOR (enum id model bất kỳ) lẫn 500 khi gọi
     * seoMetadata() lên model không có trait.
     * INPUT: Request. OUTPUT: ?Model — null khi type/id không hợp lệ
     * hoặc model không được phép SEO.
     * =====================================================================
     */
    protected function findModel(Request $request): ?Model
    {
        $type = (string) $request->input('seoable_type');
        $id = (int) $request->input('seoable_id');
        if ($type === '' || $id <= 0) {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;
        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        // 1) Chỉ các class nằm trong whitelist SEO mới được phép (chặn IDOR:
        //   enum id của model bất kỳ trong hệ thống).
        if (! $this->isSeoableModel($class)) {
            return null;
        }

        // 2) Model phải thực sự dùng HasSeoMeta — nếu không, seoMetadata()
        //   sẽ BadMethodCallException (500) khi sync/update.
        if (! in_array(HasSeoMeta::class, class_uses_recursive($class), true)) {
            return null;
        }

        return $class::find($id);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Class được phép SEO hay không (whitelist).
     * =====================================================================
     * INPUT: $class FQCN. OUTPUT: bool.
     * SIDE EFFECT: không.
     * NOTE: module nghiệp vụ gọi registerSeoableModel() trong boot() để
     * mở rộng whitelist (Post, Category, VisualStyle, ChannelGroup...).
     * =====================================================================
     */
    private function isSeoableModel(string $class): bool
    {
        return in_array($class, $this->seoableClasses(), true);
    }

    /**
     * CHỨC NĂNG: Danh sách class được phép SEO (whitelist nội bộ + registry).
     * INPUT: không. OUTPUT: string[] FQCN.
     */
    private function seoableClasses(): array
    {
        // Mặc định: các model nội dung công khai thường cần SEO.
        $defaults = [
            \Modules\Taxonomy\Models\Category::class,
            \Modules\Taxonomy\Models\Tag::class,
            \Modules\Identity\Models\ChannelGroup::class,
            \Modules\Media\Models\MediaAsset::class,
        ];

        return array_merge($defaults, app(SeoModelRegistry::class)->models());
    }
}
