<?php

namespace Modules\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Modules\Core\Contracts\SlugServiceContract;
use Modules\Core\Models\Slug;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait lắp hệ thống slug toàn cục vào một Domain Model.
 * =====================================================================
 *
 * Bất kỳ Model cần URL công khai đều `use HasSlug`. Khi tạo mới hoặc khi
 * cột nguồn (mặc định `title`, tùy biến `slugSourceColumn()`) thay đổi,
 * trait tự đồng bộ slug qua SlugService trong transaction của model —
 * giữ lại slug cũ (is_primary=false) để Redirect 301.
 *
 * Model dùng trait có thể tùy biến:
 *   - slugSourceColumn(): tên cột làm nguồn slug (mặc định 'title')
 *   - slugLocale(): locale slug (mặc định 'vi')
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - bootHasSlug(): đăng ký observer saved/deleted
 * - slugs(): morphMany tất cả slug (kể cả lịch sử)
 * - slug(): morphOne slug primary hiện hành
 * - getSlugTextAttribute(): lấy chuỗi slug primary hiện hành
 * - syncSlugNow(?string $preferred): đồng bộ slug thủ công
 * - slugSourceColumn(): tên cột nguồn (override trong model)
 * - slugLocale(): locale (override trong model)
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cột nguồn của model (title/name...) + locale
 * - OUTPUT: bản ghi slugs đồng bộ tự động; quan hệ slug()/slugs()
 * =====================================================================
 */
trait HasSlug
{
    /**
     * =====================================================================
     * CHỨC NĂNG: đăng ký model observer để tự đồng bộ slug
     * =====================================================================
     *
     * INPUT: không có tham số (Laravel gọi khi boot trait).
     * OUTPUT: void — gắn callback saved/deleted.
     *
     * SIDE EFFECT:
     * - saved(): nếu model vừa tạo hoặc cột nguồn đổi -> SlugService::syncForModel()
     * - deleted(): hạ is_primary mọi slug về false (giữ lịch sử 301)
     * =====================================================================
     */
    public static function bootHasSlug(): void
    {
        static::saved(function (Model $model): void {
            if (! $model->shouldSyncSlug()) {
                return;
            }

            app(SlugServiceContract::class)->syncForModel(
                $model,
                $model->getSlugSourceValue(),
                $model->slugLocale(),
            );
        });

        static::deleted(function (Model $model): void {
            // Giữ lịch sử slug cho redirect 301: chỉ hạ primary, không xóa dòng.
            $model->slugs()->update(['is_primary' => false]);
        });
    }

    /**
     * CHỨC NĂNG: quan hệ morphMany tới mọi slug (kể cả lịch sử).
     * INPUT: không có tham số. OUTPUT: MorphMany.
     * SIDE EFFECT: không có.
     */
    public function slugs(): MorphMany
    {
        return $this->morphMany(Slug::class, 'sluggable');
    }

    /**
     * CHỨC NĂNG: quan hệ morphOne tới slug primary hiện hành.
     * INPUT: không có tham số. OUTPUT: MorphOne (is_primary=true).
     * SIDE EFFECT: không có.
     */
    public function slug(): MorphOne
    {
        return $this->morphOne(Slug::class, 'sluggable')->where('is_primary', true);
    }

    /**
     * CHỨC NĂNG: accessor lấy chuỗi slug primary hiện hành (rỗng nếu chưa có).
     * INPUT: không có tham số. OUTPUT: string slug hoặc ''.
     * SIDE EFFECT: lazy-load quan hệ slug() nếu chưa nạp.
     */
    public function getSlugTextAttribute(): string
    {
        return (string) ($this->slug?->slug ?? '');
    }

    /**
     * CHỨC NĂNG: đồng bộ slug thủ công (gọi khi muốn đổi slug ngoài sự kiện save).
     * INPUT: ?string $preferred — slug ưu tiên (null = tự sinh từ cột nguồn).
     * OUTPUT: Slug bản ghi primary mới.
     * SIDE EFFECT: ghi/cập nhật bảng slugs qua SlugService.
     */
    public function syncSlugNow(?string $preferred = null): Slug
    {
        return app(SlugServiceContract::class)->syncForModel($this, $preferred, $this->slugLocale());
    }

    /**
     * CHỨC NĂNG: quyết định có cần đồng bộ slug sau save hay không.
     * INPUT: không có tham số.
     * OUTPUT: bool — true khi mới tạo hoặc cột nguồn vừa thay đổi.
     * SIDE EFFECT: không có.
     */
    protected function shouldSyncSlug(): bool
    {
        $source = $this->slugSourceColumn();

        // wasRecentlyCreated giữ true suốt vòng đời instance sau create(),
        // nên KHÔNG dùng làm điều kiện — mọi save() sau đó sẽ re-sync trùng.
        // Chỉ đồng bộ khi: cột nguồn vừa đổi HOẶC model chưa có slug primary.
        return $this->wasChanged($source)
            || $this->slugs()->where('is_primary', true)->doesntExist();
    }

    /**
     * CHỨC NĂNG: lấy giá trị nguồn để sinh slug.
     * INPUT: không có tham số. OUTPUT: string giá trị cột nguồn.
     * SIDE EFFECT: không có.
     */
    protected function getSlugSourceValue(): string
    {
        return (string) ($this->getAttribute($this->slugSourceColumn()) ?? $this->getKey());
    }

    /**
     * CHỨC NĂNG: tên cột làm nguồn slug — override trong model nếu cần.
     * INPUT: không có tham số. OUTPUT: string tên cột (mặc định 'title').
     * SIDE EFFECT: không có.
     */
    protected function slugSourceColumn(): string
    {
        return property_exists($this, 'slugSource') ? $this->slugSource : 'title';
    }

    /**
     * CHỨC NĂNG: locale của slug — override trong model nếu đa ngôn ngữ.
     * INPUT: không có tham số. OUTPUT: string locale (mặc định 'vi').
     * SIDE EFFECT: không có.
     */
    protected function slugLocale(): string
    {
        return property_exists($this, 'slugLocale') ? $this->slugLocale : 'vi';
    }
}
