# Tài liệu Dự án (`makerting-app`)

**Cập nhật:** 29/10/2026

Thư mục `docs/` lưu trữ toàn bộ thiết kế kiến trúc, kế hoạch triển khai, đặc tả API và quy ước phát triển của hệ thống Marketing & Tự động hóa Nội dung AI đa kênh theo kiến trúc **Laravel Modules (`nwidart/laravel-modules`)**.

---

## 1. Kế hoạch & Kiến trúc Hệ thống

| Tài liệu | Mô tả nội dung |
| :--- | :--- |
| **[PLAN.md](PLAN.md)** | **Kế hoạch tổng thể & Đặc tả kiến trúc chi tiết toàn hệ thống:** phân tầng 15 Modules (4 tầng một chiều), chi tiết bảng Database, Shared Traits, DTOs Input/Output, Registries/Adapters, luồng YouTube Motif DNA & Auto Video Edit, lập lịch đăng đa kênh (Facebook, YouTube, TikTok), chuẩn mở rộng Plugin tương lai và checklist triển khai từng bước (`Phase 0` → `Phase 6`). |
| **[architecture/ARCHITECTURE.md](architecture/ARCHITECTURE.md)** | **Đặc tả kiến trúc 4 tầng:** Nguyên tắc phụ thuộc 1 chiều, chuẩn giao tiếp Module (Traits, DTOs, Contracts, Events), kiến trúc Media 2 lớp, mô hình đa kênh và hạ tầng 5 nhóm Queue Workers. |
| **[architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md](architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md)** | **Chuẩn kiến trúc UI/UX & Backend nền tảng:** thang chữ Typography, bố cục trang Thêm/Sửa 2 cột (`8/4`), khung Dialog 3 vùng `AppDialogLayout`, viewport `PerfectScrollbar`, chuẩn `BaseResponse` và mẫu comment tiếng Việt bắt buộc cho PHP/Vue. |

---

## 2. Đặc tả Chi tiết 15 Core Modules (`docs/modules/`)

Mỗi module đều có tài liệu đặc tả cấu trúc thư mục, database schema, contracts/traits, sequence diagram luồng nghiệp vụ và UI specs:

| # | Tài liệu Module | Tầng | Mục đích & Trách nhiệm chính |
| :--- | :--- | :--- | :--- |
| 01 | **[01-core.md](modules/01-core.md)** | Tầng 0 | Foundation: Base models, ServiceResult, BaseResponse, chuẩn Comment tiếng Việt |
| 02 | **[02-identity.md](modules/02-identity.md)** | Tầng 0 | User, RBAC Roles/Permissions, gom cụm kênh `ChannelGroup` |
| 03 | **[03-settings.md](modules/03-settings.md)** | Tầng 0 | Spatie Settings, cấu hình hệ thống, AI keys, social quotas |
| 04 | **[04-task-center.md](modules/04-task-center.md)** | Tầng 0 | Async jobs monitor, health check, Telegram alerts |
| 05 | **[05-taxonomy.md](modules/05-taxonomy.md)** | Tầng 1 | Phân loại dùng chung: `HasSlug`, `HasCategories`, `HasTags` |
| 06 | **[06-media.md](modules/06-media.md)** | Tầng 1 | Quản lý media Spatie: `MediaAsset`, `HasThumbnail`, Media Picker modal |
| 07 | **[07-seo.md](modules/07-seo.md)** | Tầng 1 | Siêu dữ liệu SEO: `HasSeoMeta`, Meta title/desc, OpenGraph, Canonical |
| 08 | **[08-core-ai.md](modules/08-core-ai.md)** | Tầng 1 | Gateway AI trung tâm: Text, Structured JSON, Vision, Image, TTS, Audit Provenance |
| 09 | **[09-writing-style.md](modules/09-writing-style.md)** | Tầng 2 | Tone of voice: 16 quy tắc viết văn nghiêm ngặt, reverse-engineer văn phong, chấm điểm Quality Gate |
| 10 | **[10-visual-style.md](modules/10-visual-style.md)** | Tầng 2 | Image DNA: 5 phân vùng thị giác, Prompt 7 lớp, sinh ảnh nhất quán lưu vào Media |
| 11 | **[11-youtube-intelligence.md](modules/11-youtube-intelligence.md)** | Tầng 2 | Trinh sát kênh YouTube: bóc tách mô-típ kịch bản `ChannelMotifDna`, sinh kịch bản phân cảnh |
| 12 | **[12-video-studio.md](modules/12-video-studio.md)** | Tầng 2 | Dựng video tự động: TTS word-level, hiệu ứng Ken Burns, Audio ducking, Subtitle `.ass`, FFmpeg |
| 13 | **[13-blog.md](modules/13-blog.md)** | Tầng 3 | Bài viết ứng dụng mẫu: dùng 6 Shared Traits, quy trình viết bài 3 bước AI, Quality Gate |
| 14 | **[14-content-planner.md](modules/14-content-planner.md)** | Tầng 3 | Lịch phát hành nội dung đa kênh: Campaigns, Slots, Calendar kéo thả, dispatch tự động |
| 15 | **[15-social-publisher.md](modules/15-social-publisher.md)** | Tầng 3 | Xuất bản mạng xã hội: Facebook, YouTube, TikTok, xoay vòng OAuth App quota, Jitter rate limiter |

- Tham khảo tiêu chuẩn viết tài liệu module: **[docs/modules/TEMPLATE.md](modules/TEMPLATE.md)**
- Chỉ mục chi tiết các module: **[docs/modules/README.md](modules/README.md)**
