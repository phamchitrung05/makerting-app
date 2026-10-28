# Module: Seo (`Modules/Seo`)

- **Tầng kiến trúc:** Tầng 1 (Hạ tầng dùng chung)
- **Trách nhiệm chính:** Quản lý thẻ Meta SEO, OpenGraph, Canonical và Schema.org đa hình qua trait `HasSeoMeta`, tự động fallback sang title, slug, thumbnail nếu không nhập tay.
- **Phụ thuộc vào các module:** `Modules/Core`, `Modules/Media`, `Modules/Settings`.
- **Cung cấp cho các module:** `Blog`, `Taxonomy`, các trang đích hoặc landing page.

---

## 1. Cấu Trúc File & Thư Mục

```text
Modules/Seo/
├── app/
│   ├── Actions/
│   │   └── SyncSeoMetadataAction.php
│   ├── Http/
│   │   └── Resources/
│   │       └── SeoMetadataResource.php
│   ├── Models/
│   │   └── SeoMetadata.php
│   ├── Providers/
│   │   └── SeoServiceProvider.php
│   ├── Services/
│   │   └── SeoMetadataService.php
│   ├── Settings/
│   │   └── SeoSettings.php
│   └── Traits/
│       └── HasSeoMeta.php
├── database/
│   └── migrations/
│       └── 2026_10_29_000015_create_seo_metadata_table.php
├── resources/
│   └── js/
│       └── components/
│           └── SeoMetaCard.vue
└── routes/
    └── api.php
```

---

## 2. Thiết Kế Cơ Sở Dữ Liệu

### 2.1. Bảng `seo_metadata`
Quan hệ 1-1 đa hình:
- `id` (bigint PK)
- `seoable_type` (varchar 64) — morph alias (e.g. `'post'`, `'category'`)
- `seoable_id` (bigint)
- `focus_keyword` (varchar 160 nullable)
- `seo_title` (varchar 255 nullable) — chỉ lưu khi người dùng muốn ghi đè
- `seo_description` (varchar 500 nullable)
- `canonical_url` (varchar 2048 nullable)
- `robots_index` (bool default `true`)
- `robots_follow` (bool default `true`)
- `og_title` (varchar 255 nullable)
- `og_description` (varchar 500 nullable)
- `og_image_id` (bigint nullable FK `media_assets.id` nullOnDelete)
- `schema_org_json` (json nullable)
- `timestamps`
- Unique: `(seoable_type, seoable_id)`

---

## 3. Cơ Chế Fallback Thông Minh (`HasSeoMeta`)
Khi render thẻ HTML hoặc trả về API, nếu các trường SEO bị để trống:
1. `title` -> Tự lấy `$model->title` kết hợp mẫu tiêu đề chung từ `SeoSettings` (ví dụ: `%title% | %site_name%`).
2. `canonical_url` -> Tự lấy đường dẫn gốc kèm slug từ `HasSlug`.
3. `og_image` -> Tự lấy ảnh đại diện từ trait `HasThumbnail`.
Người viết bài không cần mất thời gian điền lại các thông tin đã có sẵn.
