<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Seo\Models\SeoMetadata;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Dịch vụ giải quyết giá trị SEO hiển thị cuối cùng
 * (fallback thông minh) cho một Domain Model.
 * =====================================================================
 *
 * Nguyên tắc: bảng seo_metadata CHỈ lưu phần người dùng ghi đè. Khi render
 * thẻ meta / trả API, giá trị trống được bù từ:
 *   - title   : model->title + cấu hình title_template (%title%|%site_name%)
 *   - description: seo_description ?? model->excerpt/description ?? default
 *   - canonical: canonical_url ?? canonical_base_url + '/' + slug (HasSlug)
 *   - og_*    : rơi về seo_title/description, og_image -> thumbnail (HasThumbnail)
 *   - robots  : robots_index/follow của model && global_index/follow toàn cục
 *
 * Nhận mảng cấu hình 'seo' (không phụ thuộc SeoSettings trực tiếp) để dễ
 * test + tránh vòng phụ thuộc container; SeoServiceProvider inject
 * SeoSettings::toArray() khi bind. Khóa đọc qua cfg(): title_template,
 * default_description, default_og_image_url, twitter_site, global_index,
 * global_follow, canonical_base_url.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - resolve()      : mảng meta đầy đủ đã fallback cho 1 model
 * - titleFor()     : thẻ <title> cuối
 * - canonicalFor() : canonical URL cuối
 * - robotsFor()    : 'index,follow'|...
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Model có (tuỳ chọn) HasSeoMeta/HasSlug/HasThumbnail + cfg array.
 * - OUTPUT: array meta đã merge fallback, sẵn sàng render <head>.
 * =====================================================================
 */
class SeoMetadataService
{
    /**
     * @param  array<string,mixed>  $config  Cấu hình nhóm 'seo' (SeoSettings::toArray()).
     */
    public function __construct(private readonly array $config = []) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Giải quyết toàn bộ thẻ meta của model (đã fallback).
     * =====================================================================
     * INPUT:
     * - $model : Model chủ (có thể không có bản ghi seo_metadata nào).
     * OUTPUT: array{
     *   title:string, description:?string, canonical:?string, robots:string,
     *   focus_keyword:?string, og_title:?string, og_description:?string,
     *   og_image:?string, twitter_site:?string, schema_org:?array }
     * =====================================================================
     */
    public function resolve(Model $model): array
    {
        $meta = $this->metadata($model);
        $title = $this->titleFor($model, $meta);
        $description = $this->descriptionFor($model, $meta);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $this->canonicalFor($model, $meta),
            'robots' => $this->robotsFor($meta),
            'focus_keyword' => $meta?->focus_keyword,
            'og_title' => $meta?->og_title ?: $title,
            'og_description' => $meta?->og_description ?: $description,
            'og_image' => $this->ogImageFor($model, $meta),
            'twitter_site' => $this->cfg('twitter_site'),
            'schema_org' => $meta?->schema_org_json,
        ];
    }

    /**
     * CHỨC NĂNG: Thẻ <title> cuối — seo_title ghi đè > model->title qua template.
     * INPUT: model + ?SeoMetadata. OUTPUT: string title.
     */
    public function titleFor(Model $model, ?SeoMetadata $meta = null): string
    {
        $meta ??= $this->metadata($model);
        if ($meta?->seo_title) {
            return $meta->seo_title;
        }

        $base = (string) ($model->title ?? $model->name ?? '');

        return str_replace(
            ['%title%', '%site_name%'],
            [$base, (string) config('app.name')],
            (string) $this->cfg('title_template', '%title% | %site_name%'),
        );
    }

    /**
     * CHỨC NĂNG: Canonical cuối — canonical_url ghi đè > base + slug.
     * INPUT: model + ?SeoMetadata. OUTPUT: ?string URL.
     */
    public function canonicalFor(Model $model, ?SeoMetadata $meta = null): ?string
    {
        $meta ??= $this->metadata($model);
        if ($meta?->canonical_url) {
            return $meta->canonical_url;
        }

        $base = rtrim((string) ($this->cfg('canonical_base_url') ?? ''), '/');
        $slug = $this->modelSlug($model);

        return ($base !== '' && $slug !== null && $slug !== '') ? "{$base}/{$slug}" : null;
    }

    /**
     * CHỨC NĂNG: Chuỗi robots — AND cờ model với cờ toàn cục.
     * INPUT: ?SeoMetadata. OUTPUT: 'index,follow'|...
     */
    public function robotsFor(?SeoMetadata $meta): string
    {
        $index = ($meta?->robots_index ?? true) && (bool) $this->cfg('global_index', true);
        $follow = ($meta?->robots_follow ?? true) && (bool) $this->cfg('global_follow', true);

        return ($index ? 'index' : 'noindex').','.($follow ? 'follow' : 'nofollow');
    }

    /**
     * CHỨC NĂNG: Description — ghi đè > excerpt/description model > mặc định.
     * INPUT: model + ?SeoMetadata. OUTPUT: ?string.
     */
    protected function descriptionFor(Model $model, ?SeoMetadata $meta): ?string
    {
        return $meta?->seo_description
            ?: ($model->seo_excerpt ?? $model->excerpt ?? $model->description ?? $this->cfg('default_description'));
    }

    /**
     * CHỨC NĂNG: Ảnh OG — og_image_id (Media) > thumbnail model > default.
     * INPUT: model + ?SeoMetadata. OUTPUT: ?string URL.
     */
    protected function ogImageFor(Model $model, ?SeoMetadata $meta): ?string
    {
        // Ưu tiên ảnh OG riêng: HasSeoMeta::resolveOgImageUrl() đọc MediaAsset.
        if (method_exists($model, 'resolveOgImageUrl') && ($url = $model->resolveOgImageUrl($meta))) {
            return $url;
        }

        if (method_exists($model, 'thumbnailUrl') && ($url = $model->thumbnailUrl('og'))) {
            return $url;
        }

        return $this->cfg('default_og_image_url');
    }

    /**
     * CHỨC NĂNG: Lấy slug hiện hành của model nếu dùng HasSlug.
     * INPUT: model. OUTPUT: ?string slug text.
     */
    protected function modelSlug(Model $model): ?string
    {
        if (method_exists($model, 'slug')) {
            return $model->slug?->slug; // quan hệ slug() morphOne của HasSlug
        }

        return $model->slug_text ?? $model->slug ?? null;
    }

    /**
     * CHỨC NĂNG: Bản ghi seo_metadata của model (nếu có quan hệ HasSeoMeta).
     * INPUT: model. OUTPUT: ?SeoMetadata.
     */
    protected function metadata(Model $model): ?SeoMetadata
    {
        return method_exists($model, 'seoMetadata')
            ? $model->seoMetadata()->first()
            : null;
    }

    /**
     * CHỨC NĂNG: Đọc khóa cấu hình 'seo' kèm mặc định.
     * INPUT: key + fallback. OUTPUT: mixed.
     */
    private function cfg(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
