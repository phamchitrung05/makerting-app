<?php

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\DTOs\SlugResolveResultDto;
use Modules\Core\Models\Slug;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng dịch vụ Slug toàn cục.
 * =====================================================================
 *
 * Bất kỳ Model nào cần URL công khai đều đi qua contract này thay vì
 * gọi thẳng SlugService — cho phép mock khi test và thay đổi cơ chế
 * sinh/lưu slug mà không ảnh hưởng module nghiệp vụ.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - syncForModel(Model $model, ?string $preferredSlug, ?string $locale): đồng bộ slug
 * - resolve(string $slug, string $sluggableType, ?string $locale): tra cứu slug
 * - preview(string $source, string $sluggableType, ?string $locale): xem trước slug khả dụng
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Model Eloquent + slug nguồn + locale
 * - OUTPUT: Slug model / SlugResolveResultDto / string slug khả dụng
 * =====================================================================
 */
interface SlugServiceContract
{
    public function syncForModel(Model $model, ?string $preferredSlug = null, ?string $locale = null): Slug;

    public function resolve(string $slug, string $sluggableType, ?string $locale = null): SlugResolveResultDto;

    public function preview(string $source, string $sluggableType, ?string $locale = null): string;
}
