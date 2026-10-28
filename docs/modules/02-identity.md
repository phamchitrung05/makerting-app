# Module: Identity (`Modules/Identity`)

- **Tầng kiến trúc:** Tầng 0 (Hạt nhân nền tảng)
- **Trách nhiệm chính:** Quản lý nhân sự nội bộ (không có Customer), phân quyền động (`Spatie Permission` + `PermissionRegistry`), và quản lý phân cụm đa kênh qua **`ChannelGroup` (Nhóm kênh / Ngách nội dung)**.
- **Phụ thuộc vào các module:** `Modules/Core`.
- **Cung cấp cho các module:** Tất cả các module cần phân quyền hoặc gắn với nhóm kênh (`SocialPublisher`, `Media`, `ContentPlanner`, `Blog`, `VideoStudio`).

---

## 1. Cấu Trúc File & Thư Mục

```text
Modules/Identity/
├── app/
│   ├── Actions/
│   │   ├── SaveChannelGroupAction.php
│   │   └── UpdateAdminPasswordAction.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── ChannelGroupController.php
│   │   │   └── RolePermissionController.php
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── ChannelGroup.php
│   │   └── User.php
│   ├── Providers/
│   │   └── IdentityServiceProvider.php
│   ├── Registries/
│   │   └── PermissionRegistry.php
│   └── Services/
│       └── ChannelGroupContextService.php
├── database/
│   ├── migrations/
│   │   ├── 2026_10_29_000004_create_channel_groups_table.php
│   │   └── 2026_10_29_000005_create_channel_group_user_table.php
│   └── seeders/
│       └── IdentityDefaultSeeder.php
├── resources/
│   └── js/
│       ├── pages/
│       │   ├── channel-groups/
│       │   └── roles/
│       ├── services/
│       └── stores/
└── routes/
    └── api.php
```

---

## 2. Thiết Kế Cơ Sở Dữ Liệu

### 2.1. Bảng `channel_groups`
Gom cụm các Fanpage, Kênh YouTube, Tài khoản TikTok cùng một thương hiệu/ngách nội dung.
- `id` (bigint PK)
- `name` (varchar 160) — Ví dụ: *"Ngách Công Nghệ & AI"*, *"Cụm Kênh Mỹ Phẩm X"*
- `code` (varchar 60 unique) — e.g. `'tech-ai'`
- `color` (varchar 20 default `'primary'`)
- `description` (text nullable)
- `defaults_json` (json) — Cấu hình mặc định cho cụm kênh:
  - `default_writing_profile_id` (FK nullable)
  - `default_visual_style_id` (FK nullable)
  - `default_motif_profile_id` (FK nullable)
  - `default_tts_voice_id` (varchar 100 nullable)
  - `watermark_media_asset_id` (bigint nullable)
  - `brand_cta_template` (text nullable)
- `is_active` (bool default `true`)
- `created_by` (FK `users.id`)
- `timestamps`

### 2.2. Bảng `channel_group_user`
Phân công nhân sự phụ trách nhóm kênh cụ thể.
- `channel_group_id` (FK cascade)
- `user_id` (FK cascade)
- `role_in_group` (`'manager' | 'editor' | 'reviewer'`)
- Unique `(channel_group_id, user_id)`

---

## 3. Các Luồng Xử Lý Chính

### 3.1. Luồng Tự Động Đăng Ký Quyền Theo Module (`PermissionRegistry`)
Mỗi module mới khi cài đặt không cần sửa file Seeder tổng. Thay vào đó, trong `ModuleServiceProvider::boot()`:
```php
$permissionRegistry->registerModule('youtube_intelligence', [
    'label' => 'Quản lý Kênh & Phân tích YouTube',
    'permissions' => [
        'youtube.view' => 'Xem danh sách kênh & video mẫu',
        'youtube.analyze' => 'Phân tích kênh & sinh Motif DNA',
        'youtube.scripts' => 'Duyệt & chỉnh sửa kịch bản',
    ],
]);
```
Trang Admin Quản lý Phân quyền tự động quét `PermissionRegistry` để hiển thị ma trận phân quyền trực quan cho người dùng.
