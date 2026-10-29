<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\SlugServiceContract;
use Modules\Core\DTOs\SlugResolveResultDto;
use Modules\Core\Models\Slug;
use Modules\Core\Registries\SlugRegistry;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Dịch vụ sinh, đồng bộ và tra cứu slug toàn cục.
 * =====================================================================
 * Sinh slug duy nhất (chống đụng độ hậu tố -1,-2), giữ lịch sử slug cũ cho
 * Redirect 301 (is_primary=false), tra cứu slug theo loại tài nguyên.
 * Ghi chạy trong DB::transaction + lockForUpdate chống race.
 * =====================================================================
 */
class SlugService implements SlugServiceContract
{
    protected const MAX_ATTEMPTS = 5;

    public function __construct(protected SlugRegistry $registry) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Đồng bộ slug cho 1 model sau khi tạo/cập nhật.
     * =====================================================================
     *
     * INPUT :
     *   - Model $model — bản ghi cần gắn slug (Post, Category, ...)
     *   - ?string $preferredSlug — slug do người dùng nhập tay (null = tự sinh
     *     từ getKey() hoặc trait HasSlug sẽ truyền source column).
     *   - ?string $locale — mã ngôn ngữ (mặc định 'vi').
     *
     * OUTPUT: Slug — bản ghi slug đang primary mới nhất cho model+locale.
     *
     * SIDE EFFECT:
     *   - Transaction + lockForUpdate quanh các bản ghi Slug hiện có.
     *   - Nếu slug thay đổi: tắt is_primary của slug cũ (giữ cho Redirect 301),
     *     tạo slug mới với is_primary=true.
     * =====================================================================
     */
    public function syncForModel(Model $model, ?string $preferredSlug = null, ?string $locale = null): Slug
    {
        $locale = $locale ?? 'vi';
        $type = $this->typeOf($model);
        $source = $preferredSlug ?? (string) $model->getKey();

        return DB::transaction(function () use ($model, $type, $source, $locale) {
            $existing = Slug::where('sluggable_type', $type)
                ->where('sluggable_id', $model->getKey())
                ->where('locale', $locale)
                ->lockForUpdate()
                ->get();

            $base = $this->baseSlug($source);

            // Nếu model đã từng sở hữu slug $base (kể cả is_primary=false do
            // đổi tiêu đề trước đó) -> kích hoạt lại slug đó thay vì tạo -1.
            $ownMatch = $existing->firstWhere('slug', $base);
            if ($ownMatch) {
                if (! $ownMatch->is_primary) {
                    Slug::where('sluggable_type', $type)
                        ->where('sluggable_id', $model->getKey())
                        ->where('locale', $locale)
                        ->update(['is_primary' => false]);

                    $ownMatch->update(['is_primary' => true]);
                }

                return $ownMatch->fresh();
            }

            // Slug chưa từng thuộc model này -> phải kiểm tra trùng toàn cục
            // (có thể thuộc model khác) và cộng hậu tố nếu cần.
            $candidate = $this->makeUnique($base, $type, $locale);

            Slug::where('sluggable_type', $type)
                ->where('sluggable_id', $model->getKey())
                ->where('locale', $locale)
                ->update(['is_primary' => false]);

            return Slug::create([
                'sluggable_type' => $type,
                'sluggable_id' => $model->getKey(),
                'slug' => $candidate,
                'locale' => $locale,
                'is_primary' => true,
            ]);
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tra cứu slug theo chuỗi + loại tài nguyên (phục vụ 301 redirect).
     * =====================================================================
     *
     * INPUT :
     *   - string $slug — slug cần resolve.
     *   - string $sluggableType — alias morph ('post', 'category', ...).
     *   - ?string $locale — mặc định 'vi'.
     *
     * OUTPUT: SlugResolveResultDto — found, sluggableId, isPrimary, primarySlug.
     *         Khi slug cũ trỏ về bản ghi đã đổi slug, trả primarySlug mới để
     *         controller redirect 301.
     *
     * SIDE EFFECT: 1-2 query SELECT vào bảng slugs.
     * =====================================================================
     */
    public function resolve(string $slug, string $sluggableType, ?string $locale = null): SlugResolveResultDto
    {
        $locale = $locale ?? 'vi';

        $record = Slug::where('sluggable_type', $sluggableType)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->first();

        if (! $record) {
            return SlugResolveResultDto::notFound($locale);
        }

        $primarySlug = $record->is_primary
            ? $record->slug
            : Slug::where('sluggable_type', $sluggableType)
                ->where('sluggable_id', $record->sluggable_id)
                ->where('locale', $locale)
                ->where('is_primary', true)
                ->value('slug');

        return new SlugResolveResultDto(
            found: true,
            sluggableType: $record->sluggable_type,
            sluggableId: (int) $record->sluggable_id,
            isPrimary: (bool) $record->is_primary,
            primarySlug: $primarySlug,
            locale: $locale,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xem trước slug sẽ được sinh (dùng cho UI gợi ý realtime).
     * =====================================================================
     *
     * INPUT : string $source (tiêu đề/tên); string $sluggableType; ?string $locale.
     * OUTPUT: string — slug duy nhất (chưa lưu DB).
     * SIDE EFFECT: chỉ SELECT để kiểm tra trùng; không ghi DB.
     * =====================================================================
     */
    public function preview(string $source, string $sluggableType, ?string $locale = null): string
    {
        return $this->makeUnique($this->baseSlug($source), $sluggableType, $locale ?? 'vi');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đảm bảo slug duy nhất trong (type, locale) bằng hậu tố số.
     * =====================================================================
     *
     * INPUT : string $base (slug đã slugify); string $type; string $locale.
     * OUTPUT: string — slug duy nhất: $base, $base-1, $base-2, ... tối đa 5
     *         lần; sau đó fallback $base-<random6>.
     *
     * SIDE EFFECT: SELECT lặp cho tới khi tìm được slug chưa dùng.
     * EXCEPTION: RuntimeException nếu fallback random vẫn trùng.
     * =====================================================================
     */
    protected function makeUnique(string $base, string $type, string $locale): string
    {
        $candidate = $base;
        $attempt = 0;

        while ($this->exists($candidate, $type, $locale)) {
            $attempt++;
            if ($attempt > self::MAX_ATTEMPTS) {
                $candidate = $base . '-' . Str::lower(Str::random(6));
                if (! $this->exists($candidate, $type, $locale)) {
                    break;
                }
                throw new \RuntimeException('Khong sinh duoc slug duy nhat.');
            }
            $candidate = $base . '-' . $attempt;
        }

        return $candidate;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra slug đã tồn tại trong (type, locale) chưa.
     * =====================================================================
     *
     * INPUT : string $slug; string $type; string $locale.
     * OUTPUT: bool — true nếu đã có bản ghi.
     * SIDE EFFECT: 1 SELECT EXISTS.
     * =====================================================================
     */
    protected function exists(string $slug, string $type, string $locale): bool
    {
        return Slug::where('sluggable_type', $type)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->exists();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa chuỗi nguồn thành slug URL-safe.
     * =====================================================================
     *
     * INPUT : string $source — tiêu đề/tên gốc.
     * OUTPUT: string — slug đã slugify, cắt 120 ký tự, không rỗng
     *         (fallback 'item' nếu source toàn ký tự đặc biệt).
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    protected function baseSlug(string $source): string
    {
        $slug = Str::slug(Str::limit(trim($source), 120, ''));

        return $slug !== '' ? $slug : 'item';
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy alias morph của model (theo SlugRegistry nếu đã đăng ký).
     * =====================================================================
     *
     * INPUT : Model $model.
     * OUTPUT: string — alias ngắn ('post','category') hoặc getMorphClass().
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    protected function typeOf(Model $model): string
    {
        $class = get_class($model);

        return $this->registry->aliasFor($class) ?? $model->getMorphClass();
    }
}
