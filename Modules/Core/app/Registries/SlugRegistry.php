<?php

namespace Modules\Core\Registries;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry ánh xạ morph-alias → Model class cho hệ thống slug.
 * =====================================================================
 *
 * Bảng `slugs.sluggable_type` lưu alias ngắn (ví dụ 'post', 'category') thay
 * vì FQCN để DB gọn và không lộ cấu trúc namespace. Module nghiệp vụ tự
 * đăng ký alias của mình trong ServiceProvider::boot() — đúng chuẩn Registry
 * Pattern: tầng dưới mở cổng, tầng trên tự đăng ký (tránh phụ thuộc vòng).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(string $alias, string $modelClass): đăng ký alias -> class
 * - resolve(string $alias): lấy class từ alias
 * - aliasFor(string $modelClass): lấy alias từ class
 * - has(string $alias): kiểm tra alias đã đăng ký
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : alias ngắn + FQCN model
 * - OUTPUT: ánh xạ hai chiều alias <-> class cho SlugService & morphTo
 * =====================================================================
 */
class SlugRegistry
{
    /**
     * Bản đồ alias => FQCN model.
     *
     * @var array<string, string>
     */
    protected array $map = [];

    /**
     * =====================================================================
     * CHỨC NĂNG: đăng ký một alias morph cho model class
     * =====================================================================
     *
     * INPUT:
     * - $alias      (string): alias ngắn lưu trong DB (vd 'post')
     * - $modelClass (string): FQCN model tương ứng
     *
     * OUTPUT:
     * - void — ghi vào $map
     *
     * SIDE EFFECT: thay đổi trạng thái registry (in-memory).
     * =====================================================================
     */
    public function register(string $alias, string $modelClass): void
    {
        $this->map[$alias] = $modelClass;

        // Đăng ký alias vào morphMap (KHÔNG dùng enforceMorphMap — nó bật chế độ
        // strict bắt mọi model phải có alias và ném ClassMorphViolationException
        // cho model chưa đăng ký, ví dụ Spatie pivot model_type). morphMap() chỉ
        // cho phép resolve alias -> class mà không ép buộc model khác.
        Relation::morphMap([$alias => $modelClass]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: lấy FQCN model từ alias
     * =====================================================================
     *
     * INPUT: $alias (string).
     * OUTPUT: ?string FQCN hoặc null nếu chưa đăng ký.
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    public function resolve(string $alias): ?string
    {
        return $this->map[$alias] ?? null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: lấy alias từ FQCN model (ngược)
     * =====================================================================
     *
     * INPUT: $modelClass (string FQCN hoặc tên morph class).
     * OUTPUT: ?string alias hoặc null nếu chưa đăng ký.
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    public function aliasFor(string $modelClass): ?string
    {
        $found = array_search($modelClass, $this->map, true);

        return $found === false ? null : $found;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: kiểm tra alias đã đăng ký chưa
     * =====================================================================
     *
     * INPUT: $alias (string).
     * OUTPUT: bool.
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    public function has(string $alias): bool
    {
        return isset($this->map[$alias]);
    }
}
