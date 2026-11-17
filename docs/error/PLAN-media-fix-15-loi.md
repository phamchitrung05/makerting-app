# PLAN — Sửa 15 lỗi module Media (code-review xhigh)

> Ngày lập: 2026-11-15  
> Phạm vi: module `Modules/Media` (backend + frontend)  
> Nguồn lỗi: báo cáo `/code-review` xhigh (10 góc tìm kiếm + dedup + sweep), 15 findings  
> Tiêu chí duyệt: mỗi lỗi chỉ được đánh dấu xong khi (a) code được sửa, (b) test cần thiết được viết/cập nhật, (c) chạy `php artisan test Modules/Media` xanh.

## Quy ước đọc file

- `[f]` = file sửa, `[t]` = file test, `[m]` = migration/console/command mới.
- Thứ tự thực thi theo lô (Batch) — lô sau có thể phụ thuộc lô trước về file chung.

## Danh sách lỗi (tóm tắt 15 findings)

| # | Mức | File | Lỗi | Trạng thái |
|---|---|---|---|---|
| 1 | Cao | `MediaAssetController.php:302` | move visibility nuốt lỗi → file private nằm public disk | ✅ Lô 1 (`4e0809b`) |
| 2 | Cao | `StoreGeneratedMediaAction.php:57` | dedupe trả asset cũ, mất `ai_generation` meta | ✅ Lô 2 |
| 3 | Trung | `GenerateAiPreviewImagesAction.php:172` | job báo `ready` khi thiếu ảnh | ✅ Lô 2 |
| 4 | Trung | `MediaAssetController.php:394` | stream gắn `Accept-Ranges` nhưng không xử lý Range | ✅ Lô 3 |
| 5 | Trung | `UploadMediaAssetAction.php:96` | dedupe bỏ qua visibility yêu cầu | ✅ Lô 1 (`4e0809b`) |
| 6 | Trung | `AiGenerateImageDialog.vue:643` | lỗi poll tạm thời làm UI mất kết quả job | ✅ Lô 2 |
| 7 | Trung | `MediaUsageService.php:138` | attachMany đếm id trùng trước khi lọc → 422 sai | ✅ Lô 3 |
| 8 | Trung | `SaveAiPreviewToMediaAction.php:66` | save song song tạo 2 asset trùng checksum | ✅ Lô 2 |
| 9 | Trung | `AiImagePreviewStore.php:399` | race read-modify-write trên Cache job tracker | ✅ Lô 2 |
| 10 | Thấp | `MediaDetailSidebar.vue:290` | share link asset private không dùng được | ✅ Lô 4 |
| 11 | Thấp | `MediaAssetController.php:72` | N+1 query ở index/options | ✅ Lô 3 |
| 12 | Thấp | `GenerateAiPreviewImagesAction.php:239` | file preview AI không bao giờ dọn | ✅ Lô 2 |
| 13 | Thấp | `MediaAssetController.php:294` | logic move disk trùng lặp 2 nơi | ✅ Lô 1 (`4e0809b`) |
| 14 | Thấp | `StoreGeneratedMediaAction.php:147` | `hash()` inline bỏ qua checksum action | ✅ Lô 2 |
| 15 | Thấp | `MediaAsset.php:46` | `HasSlug` không cần thiết trên MediaAsset | ✅ Lô 3 |
| D2 | UX | `mediaAsset.js` + `folders()` | cây thư mục thiếu node cha ảo / đa cấp | ✅ Lô 4 |

**Quyết định từ người dùng (đã xác nhận):**
- Lỗi 10 (share link): **thêm signed share URL** có TTL 60' (không ẩn nút).
- Lỗi 15 (HasSlug): **gỡ trait + migration dọn 25 slug cũ** của MediaAsset.
- Lỗi 2 (AI dedupe): **`dedupe=false` cho save AI** — mỗi ảnh AI luôn tạo MediaAsset riêng; upload thường giữ dedupe.

---

## Lô A — Quyền/Visibility (tập trung file move)

> Sửa cùng chỗ: tách `moveFileBetweenDisks` thành 1 hàm dùng chung trên `MediaAsset`, đồng thời giải quyết #1 + #13.

**A1. Trích phần di chuyển disk ra model (phục vụ #13 + #1)**
- [f] `Modules/Media/app/Models/MediaAsset.php` — thêm `public function moveMediaFileToDisk(string $newDisk): bool` chứa toàn bộ logic `media->move()` + `try/catch + Log::warning`. Trả `true/false`.
- [f] `Modules/Media/app/Http/Controllers/MediaAssetController.php` — `update()` gọi `$asset->moveMediaFileToDisk($newDisk)`; khi trả `false` → trả `BaseResponse::error('Không chuyển được file sang disk riêng tư', 502)` thay vì nuốt (chỉ áp dụng khi đổi sang `private`; sang `public` vẫn warning vì file chỉ bớt nguy hiểm).
- [f] `Modules/Media/app/Services/MediaUsageService.php` — `applyVisibility()` gọi `$asset->moveMediaFileToDisk()` thay logic nội bộ (~30 dòng xóa).
- [t] `Modules/Media/tests/Feature/MediaHttpTest.php` — thêm test `test_update_to_private_returns_502_when_move_fails` (mock `Media::move()` ném exception hoặc stub filesystem lỗi).

**A2. Chống dedupe ngược ý định (#5)**
- [f] `Modules/Media/app/Actions/UploadMediaAssetAction.php:86` — trong khối `$existing`:
  - Nếu `$visibility === MediaAsset::VISIBILITY_PRIVATE && $existing->visibility !== private` → **không trả asset cũ**; tiếp tục tạo asset mới trên `media_private` (giữ checksum trùng — checksum chỉ là index, không unique).
  - `channel_group_id`: nếu caller truyền group khác asset cũ → tạo mới thay vì trả cũ.
- [t] `Modules/Media/tests/Feature/MediaFeatureTest.php` — `test_upload_same_file_with_private_visibility_creates_new_asset`.

---

## Lô B — Luồng ảnh AI (job / save / cleanup)

**B1. Job không báo ready khi thiếu ảnh (#3)**
- [f] `Modules/Media/app/Actions/GenerateAiPreviewImagesAction.php:168-172` — sau vòng `while`:
  - `$collectedCount < $count` → `markJobFailed($jobId, 'Chỉ tạo được X/Y ảnh do nhà cung cấp', 'partial_generation')` thay vì `markJobReady` (giữ các ảnh đã tạo để user vẫn save).
  - FE dialog khi `status==='failed'` vẫn render `results` đã có.
- [f] `Modules/Media/resources/js/components/AiGenerateImageDialog.vue` — nhánh `failed` hiển thị `generatedResults` nếu `results.length > 0` kèm snackbar cảnh báo thay vì màn trống.
- [t] `Modules/Media/tests/Feature/MediaAiImageTest.php` — `test_job_failed_partial_when_provider_returns_empty`.

**B2. Chặn save song song (#8) + race trên cache (#9)**
- [f] `Modules/Media/app/Actions/SaveAiPreviewToMediaAction.php:55-70` — bọc toàn bộ `execute()` bằng `Cache::lock('save_ai_preview:'.$previewId, 10)->block(5, fn () => ...)`. Trong khoá, đọc lại preview + kiểm `saved_asset_id` trước khi ghi.
- [f] `Modules/Media/app/Services/AiImagePreviewStore.php:238-260,399-403` — thêm `withJobLock($jobId, callable)` dùng `Cache::lock('ai_image_job_lock:'.$jobId, 10)`; `appendJobResult`, `markPreviewSaved`, `updateJob` bọc bên trong.
- [m] `Modules/Media/database/migrations/2026_11_15_000015_add_unique_checksum_to_media_assets_table.php` — **KHÔNG** thêm unique index (lỗi 2 yêu cầu cho phép trùng checksum khi AI tạo nhiều asset); thay vào đó thêm comment/migration docs trong code.
- [t] `Modules/Media/tests/Feature/MediaAiImageTest.php` — `test_concurrent_save_creates_single_asset` (gọi save 2 lần song song qua `Bus::dispatch` + `Cache::shouldReceive` hoặc sequential double-call trong cùng request).

**B3. Save AI bỏ qua dedupe (#2)**
- [f] `Modules/Media/app/DTOs/StoreGeneratedMediaDto.php` — thêm `public bool $dedupe = true`.
- [f] `Modules/Media/app/Actions/StoreGeneratedMediaAction.php:56` — chỉ chạy nhánh `$existing` khi `$dto->dedupe`.
- [f] `Modules/Media/app/Actions/SaveAiPreviewToMediaAction.php:116-130` — truyền `dedupe: false` (ảnh AI luôn tạo asset riêng).
- [t] `Modules/Media/tests/Feature/MediaAiImageTest.php` — `test_save_ai_same_content_creates_separate_asset`.

**B4. Dọn file preview tạm (#12)**
- [m] `Modules/Media/app/Console/Commands/CleanupAiPreviewsCommand.php` — command `media:cleanup-ai-previews`: duyệt `temp_processing/ai-previews/*`, xóa file có mtime cũ hơn `media.ai_preview_ttl_minutes` (mặc định 60'); log số file xóa. Đăng ký `->daily()` trong `MediaServiceProvider::boot()` (hoặc `routes/console.php` nếu app đã có).
- [f] `Modules/Media/app/Actions/SaveAiPreviewToMediaAction.php` — sau khi `markPreviewSaved` thành công, gọi `$this->store->deletePreviewFile($previewId)` (thêm helper xóa `ai-previews/{id}.*`).
- [f] `Modules/Media/app/Services/AiImagePreviewStore.php` — thêm `deletePreviewFile()` + `listPreviewPaths()` helper.
- [t] `Modules/Media/tests/Feature/MediaAiImageTest.php` — `test_saved_preview_file_deleted`; `test_cleanup_command_removes_expired_previews`.

**B5. Poll FE không mất kết quả (#6)**
- [f] `Modules/Media/resources/js/components/AiGenerateImageDialog.vue:643-650` — trong catch của `pollJobStatus`: nếu `attempt < 3` thì retry `setTimeout(..., POLL_INTERVAL_MS)` thay vì dừng; chỉ báo lỗi sau 3 lần lỗi liên tiếp. Đặt `pollErrors` counter.

---

## Lô C — API/Backend còn lại

**C1. Range request cho stream (#4)**
- [f] `Modules/Media/app/Http/Controllers/MediaAssetController.php:352-395` — thay `StreamedResponse` bằng logic đọc Range:
  - `Storage::disk($disk)->size($path)` làm `$fileSize` (không tin `$media->size` cũ).
  - Parse `Range: bytes=start-end`; trả `206 Partial Content` + `Content-Range` khi hợp lệ, `416` khi ngoài tầm.
  - Stream từng chunk qua `readStream` + `fseek`/`fread` thay vì `fpassthru` toàn bộ.
  - Giữ nguyên header `nosniff`/`CSP`/`X-Content-Type-Options`.
- [t] `Modules/Media/tests/Feature/MediaHttpTest.php` — `test_stream_returns_206_with_range_header`, `test_stream_returns_416_on_out_of_range`.

**C2. N+1 trong index/options (#11)**
- [f] `Modules/Media/app/Http/Controllers/MediaAssetController.php:72` — `MediaAsset::query()->with(['media' => fn($q) => $q->where('collection_name', 'library'), 'creator', 'channelGroup'])`.
- [f] `MediaAssetController.php:132-139` (`options()`) — thêm `->with('media')` vào query; đổi `$a->mediaFile()` thành `->media` đã nạp.
- [f] `Modules/Media/app/DTOs/MediaItemDto.php:59` — `getFirstMedia` vẫn dùng được nhưng có thể thay `$asset->media->firstWhere('collection_name', 'library')` khi `media` đã eager; giữ fallback `mediaFile()`.

**C3. attachMany lọc trùng trước (#7)**
- [f] `Modules/Media/app/Services/MediaUsageService.php:138` — trước `assertAttachable`, tính `$uniqueCount = count(array_unique(array_map(fn($a) => $a->id, $assets)))`; kiểm `fits($existing, $uniqueCount)`; giữ `$seen` trong vòng lặp.
- [t] `Modules/Media/tests/Feature/MediaHttpTest.php` — `test_attach_many_accepts_duplicate_ids_within_limit`.

**C4. `checksum` helper (#14)**
- [f] `Modules/Media/app/Actions/StoreGeneratedMediaAction.php:147` — đổi `hash('sha256', $content)` thành `$this->checksum->fromContent($content)`.

**C5. Gỡ `HasSlug` khỏi MediaAsset (#15)**
- [f] `Modules/Media/app/Models/MediaAsset.php:46,51` — xóa `use HasSlug` + `protected string $slugSource`.
- [m] `Modules/Media/database/migrations/2026_11_15_000016_remove_media_asset_slugs.php` — `Slug::where('sluggable_type', (new MediaAsset)->getMorphClass())->delete()`; kiểm morph class khi chạy (`Modules\Media\Models\MediaAsset` hoặc alias đã đăng ký).
- [t] cập nhật `MediaFeatureTest` nếu có test kỳ vọng slug.

---

## Lô D — Frontend/UX

**D1. Signed share URL cho asset private (#10)**
- [m] `Modules/Media/routes/api.php` — thêm route `GET assets/{asset}/share-link` (bảo vệ `media.view` trả `{url}`) và `GET assets/{asset}/share-stream` (ngoài `auth:sanctum`, dùng `signed:relative` để phát stream).
- [f] `MediaAssetController.php` — method `shareLink()` tạo `URL::temporarySignedRoute('api.media.assets.share-stream', +60 phút, ['asset'=>$asset->id])`; `shareStream()` giống `stream()` nhưng không cần auth.
- [f] `Modules/Media/resources/js/services/mediaApi.js` — thêm `shareLink(id)` gọi endpoint mới.
- [f] `MediaDetailSidebar.vue:278-295` — khi `visibility==='private'`, nút copy gọi `shareLink(id)` thay vì ghép `window.location.origin + streamUrl`.

**D2. Cây thư mục folderTree (#9 frontend — sai cấp)**
- [f] `Modules/Media/resources/js/stores/mediaAsset.js:79-97` — khi parent không tồn tại trong map, **tạo node ảo** cho mọi cấp tiền tố (ví dụ `products`, `products/shoes`) rồi gắn con; count của parent ảo = tổng con, không hiển thị node ảo nếu người dùng đã tắt "show parents".
- [t] `Modules/Media/resources/js/stores/mediaAsset.test.js` (nếu đã có test) hoặc thêm `tests/Feature` FE coverage — hoặc đánh dấu tương đương backend `folders()` trả cả prefix cha. *(Chọn: backend `folders()` trả thêm parent ảo — đơn giản + có test)*

---

## Thứ tự thực hiện đề xuất (7 nhóm)

1. **Lô A** (A1 + A2) — file shared `MediaAsset` + controller.
2. **Lô C** (C1–C5) — độc lập phần còn lại của backend.
3. **Lô B** (B1–B5) — phức tạp nhất, làm sau khi A/C ổn.
4. **Lô D** (D1 + D2) — frontend + signed link.
5. **Test** — chạy `php artisan test Modules/Media` toàn cục.
6. **Migration** — chạy `php artisan migrate` cho 2 migration mới (000015, 000016).
7. **Đánh dấu xong** — cập nhật file này (tick ✅) + ghi log mỗi lỗi ở `docs/error/LOG-media-fix.md`.

## Rủi ro & chú ý

- **Spatie `move()` copy qua temp dir**: có thể thất bại trên Windows khi path tên tiếng Việt; test lại với file thật trước khi merge.
- **Unique index `checksum_sha256`**: KHÔNG thêm — lỗi 2 yêu cầu cho phép trùng checksum (AI tạo nhiều asset cùng nội dung).
- **`media` eager load**: Spatie relation tên `media` (morphMany `Spatie\MediaLibrary\MediaCollections\Models\Media`) — kiểm `MediaAsset::media()` tồn tại trước khi `with('media')`.
- **Signed route `signed:relative`**: đã dùng cho preview AI; tái sử dụng pattern cho share-stream, kiểm `URL::temporarySignedRoute(..., absolute: false)` hoạt động qua Vite proxy.
- **Command `media:cleanup-ai-previews`**: chỉ xóa file cũ hơn TTL; file được save trước khi TTL sẽ bị xóa nếu cleanup chạy sau khi save (giải pháp: chỉ quét `ai-previews/` cũ hơn `ttl*2` hoặc chỉ xóa khi không còn trong cache — nhưng cache mất khi TTL hết, nên quét theo mtime đơn giản đủ).
- **FE folderTree**: chọn sửa backend `folders()` để trả cả prefix cha (test dễ hơn) thay vì sửa getter FE.

## Definition of Done

- [x] 15 lỗi (+ D2 `folderTree`) được sửa theo đúng hướng đã chọn (A–D trên).
- [x] `php artisan test Modules/Media/tests` xanh (**64 passed / 427 assertions**).
- [x] `php artisan migrate` chạy thành công (`2026_11_17_000001_remove_media_asset_slugs`; bỏ qua unique checksum index theo quyết định #2/#8).
- [x] `docs/error/LOG-media-fix.md` ghi lại từng lỗi: file sửa, test đã thêm, trạng thái.
- [x] Code-review lại: `git diff` không chứa code comment out / TODO thừa; ESLint & `npm run build` xanh.
