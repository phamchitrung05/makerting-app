# LOG — Kết quả sửa 15 lỗi + D2 module Media

> Ngày hoàn tất: 2026-11-17  
> Tham chiếu kế hoạch: [PLAN-media-fix-15-loi.md](./PLAN-media-fix-15-loi.md)  
> Kết quả kiểm thử cuối cùng: `php artisan test Modules/Media/tests` — **64 passed (427 assertions)** (`MediaAiImageTest` 20, `MediaFeatureTest` 27, `MediaHttpTest` 17); `npm.cmd run build` — **2774 modules transformed (exit code 0)**.

---

## Bảng theo dõi từng lỗi

| # | Lô | Trạng thái | File sửa / tạo mới | Test đã thêm / xác minh |
|---|---|---|---|---|
| 1 | Lô 1 (A1) | ✅ Đã xong (`4e0809b`) | `MediaAsset.php`, `MediaAssetController.php` (`update`), `MediaUsageService.php` (`applyVisibility`) | `MediaHttpTest::test_update_to_private_returns_502_when_move_fails`, `MediaFeatureTest::test_attach_to_private_slot_rolls_back_when_disk_move_fails` |
| 2 | Lô 2 (B3) | ✅ Đã xong | `StoreGeneratedMediaDto.php` (`$dedupe`), `StoreGeneratedMediaAction.php`, `SaveAiPreviewToMediaAction.php` (`dedupe: false`) | `MediaAiImageTest::test_save_ai_same_content_creates_separate_asset_without_losing_ai_meta` |
| 3 | Lô 2 (B1) | ✅ Đã xong | `GenerateAiPreviewImagesAction.php` (`partial_generation`), `AiGenerateImageDialog.vue` | `MediaAiImageTest::test_job_marks_partial_generation_failed_when_provider_returns_fewer_images` |
| 4 | Lô 3 (C1) | ✅ Đã xong | `MediaAssetController.php` (`streamMediaFile`, `parseByteRange`, HTTP `200`/`206`/`416`, 64KB chunk) | `MediaHttpTest::test_stream_returns_206_with_range_header_and_416_on_out_of_range` |
| 5 | Lô 1 (A2) | ✅ Đã xong (`4e0809b`) | `UploadMediaAssetAction.php` (dedupe tôn trọng `visibility` và `channel_group_id`) | `MediaFeatureTest::test_upload_same_file_with_private_visibility_creates_new_asset`, `test_upload_same_file_with_different_channel_group_creates_new_asset` |
| 6 | Lô 2 (B5) | ✅ Đã xong | `AiGenerateImageDialog.vue` (`pollJobStatus` retry lỗi tạm thời tới `MAX_POLL_ERRORS`) | ESLint 0 errors + Vite production build xanh |
| 7 | Lô 3 (C3) | ✅ Đã xong | `MediaUsageService.php` (`attachMany` dedupe + `lockForUpdate` + đếm `$newIncoming`) | `MediaHttpTest::test_attach_many_allows_duplicate_ids_and_reordering_existing_within_limit` |
| 8 | Lô 2 (B2) | ✅ Đã xong | `AiImagePreviewStore.php` (`withPreviewSaveLock`), `SaveAiPreviewToMediaAction.php` *(giữ non-unique `checksum_sha256` theo quyết định #2)* | `MediaAiImageTest::test_cache_locks_enforce_contention_on_database_store_and_keep_job_results` |
| 9 | Lô 2 (B2) | ✅ Đã xong | `AiImagePreviewStore.php` (`withJobLock` cho tracker cache) | `MediaAiImageTest::test_cache_locks_enforce_contention_on_database_store_and_keep_job_results` |
| 10 | Lô 4 (D1) | ✅ Đã xong | `Modules/Media/routes/api.php`, `MediaAssetController.php` (`shareLink`, `shareStream`), `mediaApi.js`, `MediaDetailSidebar.vue` (`shareCurrentAsset`, `copyFieldValue`) | `MediaHttpTest::test_private_asset_share_link_and_share_stream_enforce_auth_signature_and_ttl` |
| 11 | Lô 3 (C2) | ✅ Đã xong | `MediaAssetController.php` (`index`, `options`, `show`, `store`), `MediaUsageService.php` (`usages`) eager load `media` | `MediaHttpTest::test_index_and_options_do_not_trigger_n_plus_one_media_queries` |
| 12 | Lô 2 (B4) | ✅ Đã xong | `CleanupAiPreviewsCommand.php` (`media:cleanup-ai-previews`), `MediaServiceProvider.php` (`->hourly()`), `AiImagePreviewStore.php` (`deletePreviewFile`, `cleanupExpiredTempFiles`), `MediaAiImageController.php` (`preview` fallback), `SaveAiPreviewToMediaAction.php` | `MediaAiImageTest::test_saved_preview_deletes_temp_file_and_preview_endpoint_falls_back_to_saved_asset`, `test_cleanup_command_removes_expired_temp_files_and_keeps_fresh_ones` |
| 13 | Lô 1 (A1) | ✅ Đã xong (`4e0809b`) | `MediaAsset.php` (`moveMediaFileToDisk`, `diskForVisibility`), `MediaAssetController.php`, `MediaUsageService.php` | `MediaHttpTest::test_update_visibility_moves_file_between_public_and_private_disks` |
| 14 | Lô 2 (C4) | ✅ Đã xong | `StoreGeneratedMediaAction.php` (`$this->checksum->fromContent($content)`) | `MediaFeatureTest::test_store_generated_saves_ai_meta` |
| 15 | Lô 3 (C5) | ✅ Đã xong | `MediaAsset.php` (gỡ `HasSlug` + `$slugSource`), migration `2026_11_17_000001_remove_media_asset_slugs.php` | `MediaFeatureTest::test_creating_and_updating_media_asset_does_not_create_slug_records` (`php artisan migrate` đã dọn 73 slug cũ về 0) |
| D2 | Lô 4 (D2) | ✅ Đã xong | `MediaAssetController.php` (`folders`, `applyFolderFilter`), `stores/mediaAsset.js` (`folderTree`), `MediaFolderTreeItem.vue`, `pages/index.vue` | `MediaHttpTest::test_folders_expands_nested_parent_prefixes_and_filters_descendants` + Vite build |
