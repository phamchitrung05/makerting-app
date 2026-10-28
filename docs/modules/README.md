# Danh Mục Tài Liệu Cấu Trúc Các Module (`docs/modules/`)

Thư mục này chứa toàn bộ các file tài liệu đặc tả cấu trúc, database, DTOs và luồng xử lý chi tiết cho từng Module trong hệ thống. Mỗi khi phát triển hoặc cập nhật một module, developer/AI sẽ cập nhật file tương ứng để bất kỳ ai vào sau cũng hiểu ngay cách vận hành.

- **Mẫu chuẩn để viết tài liệu module:** Xem tại [TEMPLATE.md](TEMPLATE.md).

---

## Danh Sách Module Theo 4 Tầng Kiến Trúc

### Tầng 0: Hạt Nhân Nền Tảng Đa Tài Khoản Nội Bộ
1. **[01-core.md](01-core.md)** (`Modules/Core`): Chuẩn HTTP `BaseResponse`, hệ thống `slugs` + `HasSlug`, `CredentialVault`, `oauth_client_apps`, chống SSRF, `Idempotency` và `AccountRateLimiter`.
2. **[02-identity.md](02-identity.md)** (`Modules/Identity`): Xác thực nhân sự nội bộ (`Sanctum`), `PermissionRegistry` động và quản lý Nhóm kênh / Ngách nội dung (`ChannelGroup`).
3. **[03-settings.md](03-settings.md)** (`Modules/Settings`): Cấu hình Spatie Settings, `SettingsRegistry` tự động đăng ký, khóa phiên bản `version` (409) và cấu hình mặc định theo `ChannelGroup`.
4. **[04-task-center.md](04-task-center.md)** (`Modules/TaskCenter`): Quản lý tiến trình ngầm toàn hệ thống (`system_task_runs`), theo dõi chi phí AI (`ai_usage_logs`) và cảnh báo token/checkpoint qua Telegram.

### Tầng 1: Hạ Tầng Dùng Chung & Trái Tim AI
5. **[05-taxonomy.md](05-taxonomy.md)** (`Modules/Taxonomy`): Cây danh mục `categories` và thẻ `tags` dùng chung đa hình, phân vùng `scope`, trait `HasTaxonomy`.
6. **[06-media.md](06-media.md)** (`Modules/Media`): Quản lý file 2 tầng (Spatie `MediaAsset` + `media_asset_usages`), `MediaSlotRegistry`, các trait `HasThumbnail`, `HasGallery`, `HasVideoAsset`, Popup chọn/upload/tạo ảnh AI.
7. **[07-seo.md](07-seo.md)** (`Modules/Seo`): Quản lý thẻ Meta, OpenGraph, Canonical đa hình qua trait `HasSeoMeta`.
8. **[08-core-ai.md](08-core-ai.md)** (`Modules/CoreAi`): Trạm điều phối AI duy nhất (`CoreAiGatewayContract`), quản lý Providers/Models (OpenAI, Gemini, Anthropic), sổ cái `ai_runs` và trait `HasAiProvenance`.

### Tầng 2: Giải Mã DNA & Sản Xuất Sáng Tạo Tự Động
9. **[09-writing-style.md](09-writing-style.md)** (`Modules/WritingStyle`): Phân tích bài mẫu trích xuất 16 quy tắc giọng điệu, kiểm chứng trích dẫn thật chống bịa đặt, xuất snapshot không đạo văn.
10. **[10-visual-style.md](10-visual-style.md)** (`Modules/VisualStyle`): Vision AI giải mã ảnh thành 5 nhóm DNA + góc chụp `VisualStyleShot`, ghép 7 lớp prompt tạo ảnh đồng bộ thương hiệu.
11. **[11-youtube-intelligence.md](11-youtube-intelligence.md)** (`Modules/YoutubeIntelligence`): Quét kênh YouTube mẫu, bóc tách phụ đề/audio, đúc kết `ChannelMotifDna` (Hook, nhịp, tiêu đề, DNA Thumbnail) và sinh kịch bản phân cảnh `VideoScript`.
12. **[12-video-studio.md](12-video-studio.md)** (`Modules/VideoStudio`): Xưởng edit video tự động qua queue `video-render`: sinh giọng đọc TTS + phụ đề nhảy chữ karaoke + vẽ ảnh DNA từng scene + FFmpeg tạo chuyển động và nhạc nền.

### Tầng 3: Sản Xuất Nội Dung, Lập Lịch Plan & Đăng Đa Nền Tảng
13. **[13-blog.md](13-blog.md)** (`Modules/Blog`): Quản lý bài viết website, lắp ghép đủ 6 Trait chuẩn, pipeline AI viết bài 3 bước kèm chấm điểm chất lượng `> 4.0/5`.
14. **[14-content-planner.md](14-content-planner.md)** (`Modules/ContentPlanner`): Lập lịch chiến dịch theo ngày/tuần/tháng cho cả bài viết và video, tự động kích hoạt tạo nháp trước giờ lên sóng.
15. **[15-social-publisher.md](15-social-publisher.md)** (`Modules/SocialPublisher`): Quản lý đa tài khoản mạng xã hội, 3 máy trạng thái độc lập, và 3 Driver đăng tự động thật: **Facebook (Feed + Reels)**, **YouTube (Video + Shorts)**, **TikTok (API v2)**.
