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

            $candidate = $this->makeUnique($this->baseSlug($source), $type, $locale);

            $currentPrimary = $existing->firstWhere('is_primary', true);
            if ($currentPrimary && $currentPrimary->slug === $candidate) {
                return $currentPrimary;
            }

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

    public function preview(string $source, string $sluggableType, ?string $locale = null): string
    {
        return $this->makeUnique($this->baseSlug($source), $sluggableType, $locale ?? 'vi');
    }

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

    protected function exists(string $slug, string $type, string $locale): bool
    {
        return Slug::where('sluggable_type', $type)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->exists();
    }

    protected function baseSlug(string $source): string
    {
        $slug = Str::slug(Str::limit(trim($source), 120, ''));

        return $slug !== '' ? $slug : 'item';
    }

    protected function typeOf(Model $model): string
    {
        $class = get_class($model);

        return $this->registry->aliasFor($class) ?? $model->getMorphClass();
    }
}
