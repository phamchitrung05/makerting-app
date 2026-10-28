# Module: Taxonomy (`Modules/Taxonomy`)

- **Tầng kiến trúc:** Tầng 1 (Hạ tầng dùng chung)
- **Trách nhiệm chính:** Quản lý Danh mục (`categories`) dạng cây và Thẻ (`tags`) phẳng dùng chung đa hình cho mọi tài nguyên (`Post`, `VisualStyle`, `YoutubeRefChannel`, `Campaign`), hỗ trợ phân vùng (`scope`) và tự động gắn thẻ mới do AI gợi ý.
- **Phụ thuộc vào các module:** `Modules/Core`.
- **Cung cấp cho các module:** `Blog`, `VisualStyle`, `YoutubeIntelligence`, `ContentPlanner`.

---

## 1. Cấu Trúc File & Thư Mục

```text
Modules/Taxonomy/
├── app/
│   ├── Actions/
│   │   ├── SaveCategoryAction.php
│   │   └── SaveTagAction.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CategoryController.php
│   │   │   └── TagController.php
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── Category.php
│   │   └── Tag.php
│   ├── Providers/
│   │   └── TaxonomyServiceProvider.php
│   ├── Registries/
│   │   └── TaxonomyRegistry.php
│   └── Traits/
│       └── HasTaxonomy.php
├── database/
│   └── migrations/
│       ├── 2026_10_29_000009_create_categories_table.php
│       ├── 2026_10_29_000010_create_tags_table.php
│       ├── 2026_10_29_000011_create_categorizables_table.php
│       └── 2026_10_29_000012_create_taggables_table.php
├── resources/
│   └── js/
│       └── components/
│           └── TaxonomySelectorField.vue
└── routes/
    └── api.php
```

---

## 2. Thiết Kế Cơ Sở Dữ Liệu

### 2.1. Bảng `categories`
- `id` (bigint PK)
- `parent_id` (self-FK `nullOnDelete`)
- `scope` (varchar 40 nullable) — e.g. `null` (dùng chung), `'blog'`, `'visual_style'`, `'youtube'`, `'campaign'`
- `name` (varchar 160)
- `description` (text nullable)
- `status` (`'active' | 'inactive'`)
- `sort_order` (int default 0)
- `show_on_menu` (bool default `false`)
- `timestamps`, `softDeletes`
- *Gắn Trait:* `HasSlug`, `HasThumbnail`.

### 2.2. Bảng `tags`
- `id` (bigint PK)
- `scope` (varchar 40 nullable)
- `name` (varchar 120)
- `description` (text nullable)
- `status` (`'active' | 'inactive'`)
- `timestamps`, `softDeletes`
- *Gắn Trait:* `HasSlug`.

### 2.3. Bảng `categorizables` & `taggables`
- `categorizables`: `id`, `category_id` (FK cascade), `categorizable_type` (varchar 64), `categorizable_id` (bigint), `sort_order` (int default 0), `timestamps`. Unique: `(category_id, categorizable_type, categorizable_id)`.
- `taggables`: `id`, `tag_id` (FK cascade), `taggable_type` (varchar 64), `taggable_id` (bigint), `timestamps`. Unique: `(tag_id, taggable_type, taggable_id)`.

---

## 3. Trait `HasTaxonomy` & Cơ Chế Đồng Bộ Với Gợi Ý AI
Khi người dùng hoặc AI gửi payload gồm cả ID sẵn có và Tên mới đề xuất:
```php
$post->syncTaxonomyWithNew([
    'categories' => [1, 5],
    'new_categories' => ['AI Video Production'], // Tự firstOrCreate + sinh slug
    'tags' => [12, 18],
    'new_tags' => ['FFmpeg', 'CapCut Style'],
]);
```
Toàn bộ chạy trong Transaction của caller, tự chuẩn hóa chữ thường và gắn liên kết đa hình ngay lập tức.
