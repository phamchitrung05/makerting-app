# Kế hoạch nối API cho Dialog Tạo ảnh AI (`AiGenerateImageDialog`)

> **File đích UI:** [AiGenerateImageDialog.vue](../Modules/Media/resources/js/components/AiGenerateImageDialog.vue) & [index.vue](../Modules/Media/resources/js/pages/index.vue)
> **Phạm vi:** Giai đoạn 1 — nối luồng tạo ảnh AI thực tế từ dialog qua `Modules/Media` → `CoreAiGatewayContract` → `StoreGeneratedMediaAction`.
> **Nguyên tắc giữ nguyên:** Các field/thao tác trên UI chưa có dữ liệu động hoặc chưa hoàn thiện module nguồn (như `VisualStyle` P2-03, LLM gợi ý prompt, thích ảnh, ảnh tham chiếu trên driver ảnh) **đều giữ nguyên trạng thái tĩnh**, không xóa và không gượng ép chế backend giả.

---

## 1. Bảng phân loại từng field & thành phần trên Dialog

| STT | Thành phần / Field trên UI | Trạng thái hiện tại | Quyết định | Lý do & Cách xử lý chi tiết |
|---|---|---|---|---|
| 1 | **`Mô tả hình ảnh`** (`promptText`) + nút **`Gợi ý prompt`** (`applySamplePrompt`) | Chưa có API LLM suggest riêng | **Giữ nguyên tĩnh** | Giữ nguyên textarea nhập mô tả và danh sách `samplePrompts` xoay vòng tĩnh ở client. Giá trị `promptText` có thể gửi kèm như metadata tham khảo (`description`) để gợi ý `title` khi lưu vào thư viện, hoặc để người dùng copy/sửa sang ô `Prompt`. |
| 2 | **`Prompt`** (`aiPromptText`) | Đã có đầu vào `AiImageRequest::$prompt` | **Nối API** | Là nội dung chính gửi sang `CoreAiGatewayContract::generateImage()`. Validate không rỗng, tối đa 2000 ký tự, hiển thị lỗi 422 từ server dưới field nếu không hợp lệ. |
| 3 | **`Ảnh tham chiếu`** (`referenceImage`: chọn/kéo-thả ảnh cục bộ) | `AiImageRequest` có `$referenceImages` nhưng driver chưa đọc | **Giữ nguyên tĩnh** | Giữ nguyên UI chọn/kéo-thả ảnh tham chiếu xem trước cục bộ (chỉ định dạng ảnh hợp lệ, tối đa 10 MB). Các driver [OpenAiDriver.php](../Modules/CoreAi/app/Drivers/OpenAiDriver.php), [GeminiDriver.php](../Modules/CoreAi/app/Drivers/GeminiDriver.php) và [XpikiDriver.php](../Modules/CoreAi/app/Drivers/XpikiDriver.php) hiện chưa tiêu thụ `$request->referenceImages` trong hàm `image()`, nên giữ nguyên tĩnh ở Giai đoạn 1. |
| 4 | **`Tỷ lệ khung hình`** (`selectedRatio`: `1:1`, `4:3`, `3:4`, `16:9`, `9:16`) | Đã có đầu vào `AiImageRequest::$aspectRatio` | **Nối API** | Gửi nguyên giá trị `aspect_ratio` sang gateway. **Lưu ý driver thực tế:** [GeminiDriver.php](../Modules/CoreAi/app/Drivers/GeminiDriver.php#L341-L347) hỗ trợ đủ 5 tỷ lệ; [OpenAiDriver.php](../Modules/CoreAi/app/Drivers/OpenAiDriver.php#L482-L490) chỉ map `1:1`, `16:9`, `9:16` (`4:3`/`3:4` fallback về `1024x1024`); [XpikiDriver.php](../Modules/CoreAi/app/Drivers/XpikiDriver.php#L50-L51) gửi `aspect_ratio` khi khác `1:1`. Có thể hiện ghi chú nhỏ trên UI nếu model thuộc driver OpenAI không hỗ trợ `4:3`/`3:4`. |
| 5 | **`Phong cách hình ảnh`** (`selectedStyle`: 5 thẻ `visualStyles`) | `Modules/VisualStyle` (P2-03) chưa hoàn thiện API | **Giữ nguyên tĩnh** | [VisualStyleController.php](../Modules/VisualStyle/app/Http/Controllers/VisualStyleController.php) hiện vẫn là khung placeholder (`view('visualstyle::index')`), chưa có dữ liệu/API động. Giữ nguyên UI 5 thẻ chọn tĩnh để bảo toàn thiết kế, **chưa tự nối prefix vào `Prompt`** nhằm tránh làm sai prompt người dùng tự nhập (khi triển khai `P2-03`/`P2-04` sẽ thay bằng dữ liệu từ `VisualStyle`). |
| 6 | **`Model tạo ảnh`** (`selectedModel`) | Đã có `AiModel` + `CAP_IMAGE_GENERATION` + `AiSettings` | **Nối API** | Thay mảng tĩnh `imageModelOptions` bằng dữ liệu thật từ DB `ai_models` có capability `image_generation` (`AiModel::CAP_IMAGE_GENERATION`) và đang `usable()`. API trả kèm `default_model_id` (từ `AiSettings->default_image_model_id`) để tự chọn sẵn khi mở dialog. |
| 7 | **`Số lượng ảnh tạo`** (`batchCount`: `1`, `2`, `4`) | Hỗ trợ qua điều phối ở `Media` Action | **Nối API** | Gửi `count` (`1`, `2`, `4`). **Lưu ý driver `CoreAi`:** [OpenAiDriver.php](../Modules/CoreAi/app/Drivers/OpenAiDriver.php#L207-L221) gán cứng `'n' => 1` và bỏ qua `options['n']`; [XpikiDriver.php](../Modules/CoreAi/app/Drivers/XpikiDriver.php#L48-L59) gửi 1 task/lần; chỉ `GeminiDriver` hỗ trợ `options['sampleCount']`. Do đó, tầng Action của `Media` sẽ xử lý gom đủ `count` ảnh bằng cách gọi `generateImage()` lặp đến khi đủ `count` ảnh (kèm tracking tiến độ), đảm bảo đồng nhất mọi driver mà không phải phá vỡ contract hiện tại của `CoreAi`. |
| 8 | **Panel `Kết quả tạo ảnh` + nút `Tạo lại` / Tạo ảnh** | Đang render 9 SVG demo tĩnh | **Nối API** | Thay thế dần SVG demo bằng ảnh thật trả về từ API (`<img>` từ URL preview tạm + hiển thị `width × height` / `mime` / `revised_prompt` nếu có). Bấm **Tạo ảnh** / **Tạo lại** sẽ gọi API sinh lô ảnh mới theo tham số bên trái. Khi mới mở dialog (chưa tạo lần nào), nên hiển thị empty state hướng dẫn bấm **Tạo ảnh** thay vì hiển thị SVG demo dễ nhầm với ảnh AI thật. |
| 9 | **Nút `Yêu thích`** (`toggleLike(item)`) | `media_assets` chưa có cột/API favorite | **Giữ nguyên tĩnh** | Giữ trạng thái tim cục bộ theo phiên mở dialog để người dùng đánh dấu phương án ưng ý trước khi chọn lưu/tải. |
| 10 | **Nút `Lưu vào thư viện`** (`saveToLibrary`) | Đã có [StoreGeneratedMediaAction.php](../Modules/Media/app/Actions/StoreGeneratedMediaAction.php) | **Nối API** | Chỉ lưu **ảnh đang chọn** (`selectedResultId`) vào `media_assets` (tránh tạo rác các ảnh còn lại trong thư viện). Ghi nhận đúng `folder` đang chọn ở trang Media (`activeFolder || 'root'`), chạy qua `ScanMediaSecurityAction` + `checksum_sha256` dedupe + gắn `custom_properties['ai_generation']`. |
| 11 | **Nút `Tải về ảnh đã chọn`** (`downloadSelected` / `downloadItem`) | Hiện chỉ hiện `VAlert` mô phỏng | **Nối API** | Tải trực tiếp file ảnh binary thật (`.png` / `.jpg` / `.webp`) của phương án đang chọn về máy trình duyệt thông qua preview URL đã ký hoặc Blob URL. |

---

## 2. Kiến trúc & Các điểm thiết kế cần chốt

### 2.1. Quan hệ phụ thuộc giữa `Modules/Media` và `Modules/CoreAi`
Theo [ARCHITECTURE.md](architecture/ARCHITECTURE.md#L12-L94), cả `Media` và `CoreAi` cùng nằm ở **Tầng 1**, `CoreAi` chỉ phụ thuộc Tầng 0 (`Core`, `Settings`, `TaskCenter`) và **không import `Media`**. Do đó, `Media` gọi `CoreAiGatewayContract` (tương tự `Seo` → `Media` cùng Tầng 1) là hợp lệ và không gây phụ thuộc vòng.
- Tuyệt đối không gọi trực tiếp HTTP driver OpenAI/Gemini từ `Media`; mọi lời gọi sinh ảnh đều đi qua `CoreAiGatewayContract::generateImage(AiImageRequest)`.
- Tạo endpoint danh sách model riêng dưới prefix Media (`GET /api/v1/admin/media/ai-image/models`, quyền `media.manage`) thay vì gọi `/api/v1/admin/core-ai/models/options` để:
  1. Nhân sự có quyền `media.manage` dùng được ngay mà không cần cấp thừa quyền `coreai.view`.
  2. Trả về đúng `id` (PK `ai_models.id`), `provider_key`, `provider_name`, `driver`, `remote_model_id`, `is_default` — giúp [ModelResolver.php](../Modules/CoreAi/app/Services/ModelResolver.php#L185-L207) định danh chính xác provider + model ngay cả khi 2 provider cùng khai báo một `remote_model_id`.

### 2.2. Luồng Preview Tạm → Lưu có chọn lọc (`temp_processing` + Signed URL)
Vì dialog cho phép tạo 1–4 ảnh để xem trước rồi mới quyết định **Lưu vào thư viện** 1 ảnh:
1. Sau khi `CoreAiGatewayContract::generateImage()` trả `AiImageResponse` (mảng binary `['mime', 'data', 'width', 'height']`):
   - Nếu `width` / `height` trả về `null` (như ở `GeminiDriver` / `OpenAiDriver` / `XpikiDriver`), backend dùng `getimagesizefromstring($bin)` để lấy kích thước thật trước khi trả UI.
   - Ghi file tạm vào disk `temp_processing` (đã cấu hình sẵn tại [filesystems.php](../config/filesystems.php#L79-L84)) theo đường dẫn `ai-previews/{userId}/{jobId}/{index}.{ext}`.
   - Lưu metadata của từng `preview_id` (UUID) vào Cache (TTL 60 phút): `{ user_id, disk: 'temp_processing', path, mime, width, height, prompt, description, aspect_ratio, ai_model_id, remote_model_id, provider_key, ai_run_id, revised_prompt, saved_asset_id: null }`.
2. Stream preview bằng **Laravel Temporary Signed Route** (`GET /api/v1/admin/media/ai-image/previews/{previewId}` với middleware `signed`):
   - Giúp thẻ `<img :src="item.preview_url">` và `<a :href="item.download_url">` hoạt động trực tiếp với xác thực chữ ký có thời hạn (60 phút), không bị vướng thiếu header `Authorization: Bearer` trên thẻ `<img>` và không làm phình JSON response bằng chuỗi base64 nhiều megabyte.
   - Khi người dùng bấm **Lưu vào thư viện**, request `POST /api/v1/admin/media/ai-image/save` vẫn đi qua `auth:sanctum` + `permission:media.manage`, kiểm tra `Cache` đúng `$meta['user_id'] === $request->user()->id`, đọc binary từ `temp_processing` và gọi `StoreGeneratedMediaAction::executeContent($binary, $dto)`.

### 2.3. Xử lý Bất đồng bộ (`202 Accepted` + Polling) hay Đồng bộ?
Theo [VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md](architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md#L627) và [PLAN.md](PLAN.md#L538), tác vụ AI nặng cần đẩy vào Queue (`HTTP 202 Accepted` + polling) để tránh timeout HTTP request (đặc biệt khi tạo 4 ảnh tuần tự hoặc gặp `XpikiDriver` poll task).
- **Hướng đề xuất mặc định:** Dùng `GenerateAiImageJob` trả **HTTP 202** `{ job_id, status: 'queued', total: count, completed: 0, results: [] }` và FE poll `GET /api/v1/admin/media/ai-image/jobs/{jobId}` mỗi 1.5 giây.
- **Lưu ý vận hành Queue thực tế trong repo:** Trong [composer.json](../composer.json#L56), lệnh `composer dev` chạy `php artisan queue:listen --tries=1` (chỉ nghe hàng đợi **`default`**). Vì vậy, `GenerateAiImageJob` nên chạy trên queue cấu hình được qua `config('media.ai_image_queue', 'default')` (mặc định `'default'`) để chạy mượt ngay với `composer dev` và `QUEUE_CONNECTION=sync` khi test.

### 2.4. Hai điểm đã chốt trước khi triển khai code
1. **Trạng thái ban đầu cột phải khi mở Dialog:** **Đã chốt Phương án 1** — dùng empty state kèm nút **"Tạo ảnh"** khi chưa tạo lần nào (không dùng ảnh mẫu tĩnh). *(Triển khai ở Task 2 - Frontend).*
2. **Cơ chế gọi AI:** **Đã chốt Phương án A** — chạy bất đồng bộ **Queue `HTTP 202 Accepted` + Polling**, cấu hình tên queue qua `config('media.ai_image_queue', 'default')` và TTL ảnh preview tạm qua `config('media.ai_preview_ttl_minutes', 60)`. *(Đã triển khai ở Task 1 - Backend).*

---

## 3. Các bước triển khai chi tiết

### Bước 1 — Backend `Modules/Media`: Routes, Config & Endpoints *(Đã hoàn thành)*
1. **Cập nhật [config.php](../Modules/Media/config/config.php):**
   - Thêm cấu hình `ai_image_queue` (mặc định `env('MEDIA_AI_IMAGE_QUEUE', 'default')`) và `ai_preview_ttl_minutes` (mặc định `60`).
2. **Bổ sung routes trong [api.php](../Modules/Media/routes/api.php):**
   - Trong nhóm `auth:sanctum` + `prefix('v1/admin/media')` + `permission:media.manage`:
     - `GET ai-image/models` → `MediaAiImageController@models` (lấy danh sách model khả dụng có `CAP_IMAGE_GENERATION` + `default_model_id`).
     - `POST ai-image/generate` → `MediaAiImageController@generate` (validate `GenerateAiImageRequest`, tạo tracker Cache `job_id`, dispatch `GenerateAiImageJob`, trả `BaseResponse::accepted`).
     - `GET ai-image/jobs/{jobId}` → `MediaAiImageController@status` (kiểm tra ownership `user_id`, trả trạng thái `queued | processing | ready | failed` + danh sách `results` với `preview_url` ký hạn).
     - `POST ai-image/save` → `MediaAiImageController@save` (validate `SaveGeneratedImageRequest`: `preview_id`, `folder`, `title?`, `alt_text?`, `visibility?`; gọi action lưu vào `MediaAsset` qua `StoreGeneratedMediaAction::executeContent`, trả `MediaAssetResource`).
   - Ngoài `auth:sanctum` (dùng middleware `signed`):
     - `GET ai-image/previews/{previewId}` → `MediaAiImageController@preview` (stream ảnh từ `temp_processing`, hỗ trợ `?download=1` để tải file về máy).
3. **Tạo FormRequests (`Modules/Media/app/Http/Requests/`):**
   - `GenerateAiImageRequest`:
     - `prompt`: `required|string|max:2000`
     - `description`: `nullable|string|max:1000` (metadata phụ, không bắt buộc)
     - `aspect_ratio`: `required|string|in:1:1,4:3,3:4,16:9,9:16`
     - `ai_model_id`: `nullable|integer|exists:ai_models,id` (nullable = dùng model mặc định hệ thống trong `ModelResolver`)
     - `count`: `required|integer|in:1,2,4`
   - `SaveGeneratedImageRequest`:
     - `preview_id`: `required|string|uuid`
     - `folder`: `nullable|string|max:60`
     - `title`: `nullable|string|max:255`
     - `alt_text`: `nullable|string|max:255`
     - `visibility`: `nullable|in:public,private`
4. **Tạo Actions & Job (`Modules/Media/app/Actions/`, `Modules/Media/app/Jobs/`):**
   - `GenerateAiPreviewImagesAction`:
     - Nếu có `ai_model_id`, query `AiModel::query()->usable()->withCapability(AiModel::CAP_IMAGE_GENERATION)->with('provider')->find($aiModelId)`; nếu không usable/không có capability → ném lỗi nghiệp vụ rõ ràng (422).
     - Chuẩn bị `AiImageRequest` (`prompt`, `aspectRatio`, `providerKey: $model?->provider?->key`, `modelId: $model?->remote_model_id`, `module: 'media'`, `operation: 'ai_dialog_generate'`).
     - Xử lý sinh đủ `$count` ảnh (nếu 1 lần gọi trả về 1 ảnh thì lặp gọi tối đa `$count` lần; mỗi ảnh thành công ghi ngay vào `temp_processing` + Cache `preview_id` + cập nhật progress vào Cache `job_id` để FE thấy số lượng tiến triển). Bắt `AiGatewayException` ghi rõ thông báo lỗi thân thiện.
     - Lấy `width`/`height` thực qua `getimagesizefromstring()` nếu driver chưa điền.
   - `SaveAiPreviewToMediaAction`:
     - Đọc metadata `preview_id` từ Cache, kiểm tra quyền sở hữu `user_id`.
     - Nếu `saved_asset_id` đã tồn tại → trả luôn `MediaAsset` cũ (chống double-click lưu trùng).
     - Đọc binary từ `Storage::disk('temp_processing')`, khởi tạo `StoreGeneratedMediaDto`:
       - `kind: MediaAsset::KIND_IMAGE`
       - `title`: ưu tiên `title` từ request → rút gọn từ `description` hoặc `prompt` (tối đa 120 ký tự)
       - `altText`: `alt_text` hoặc mô tả ngắn
       - `folder`: `$folder ?: 'root'`
       - `visibility`: `$visibility ?: MediaAsset::VISIBILITY_PUBLIC`
       - `createdBy`: `$userId`
       - `source`: `'ai_dialog'`
       - `meta`: `['prompt', 'revised_prompt', 'description', 'aspect_ratio', 'ai_model_id', 'remote_model_id', 'provider_key', 'ai_run_id']`
     - Gọi `StoreGeneratedMediaAction::executeContent($binary, $dto)` → cập nhật `saved_asset_id` vào Cache → trả `MediaAsset`.
   - `GenerateAiImageJob` (`ShouldQueue`): gọi `GenerateAiPreviewImagesAction`, cập nhật trạng thái `processing` / `ready` / `failed` vào Cache theo `jobId`.
5. **(Bổ sung đồng bộ trong [MediaAssetController.php](../Modules/Media/app/Http/Controllers/MediaAssetController.php#L85-L91)):**
   - Hoàn thiện nhánh lọc `->when($request->query('quick') === 'ai', ...)` đã có ghi chú trong code (lọc asset có `custom_properties->ai_generation` trong media `library`) để chip **"Ảnh AI"** cột trái trang Media hiển thị đúng ảnh vừa lưu từ AI.

### Bước 2 — Frontend `Modules/Media`: API Service & Tích hợp Dialog *(Đã hoàn thành — kèm `HasAiProvenance` trên `MediaAsset`)*
1. **Mở rộng [mediaApi.js](../Modules/Media/resources/js/services/mediaApi.js):**
   - `aiImageModels()` → `GET /v1/admin/media/ai-image/models`
   - `generateAiImages(payload)` → `POST /v1/admin/media/ai-image/generate`
   - `aiImageJobStatus(jobId)` → `GET /v1/admin/media/ai-image/jobs/${jobId}`
   - `saveAiImage(payload)` → `POST /v1/admin/media/ai-image/save`
2. **Cập nhật [AiGenerateImageDialog.vue](../Modules/Media/resources/js/components/AiGenerateImageDialog.vue):**
   - **Props & Emits:**
     - Giữ `isOpen = defineModel({ type: Boolean, default: false })`.
     - Thêm prop `folder` (`type: String, default: 'root'`) để lưu thẳng vào thư mục đang mở trên trang Media.
     - Thêm emit `saved` (`defineEmits(['saved'])`) truyền `MediaAssetResource` vừa lưu về trang cha.
   - **Khi mở dialog (`watch(isOpen)`):**
     - Nạp danh sách model thật từ `mediaApi.aiImageModels()` (cache trong phiên component, loading indicator trên `Model tạo ảnh`). Nếu chưa có model khả dụng nào (`models.length === 0`), hiển thị `VAlert` cảnh báo rõ (kèm disable nút tạo ảnh) thay vì fail âm thầm.
     - Tự chọn `default_model_id` nếu nằm trong danh sách usable, ngược lại chọn model đầu tiên.
   - **Các field giữ nguyên tĩnh:**
     - `Mô tả hình ảnh` (`promptText`) + nút `Gợi ý prompt` (`applySamplePrompt`) — giữ nguyên.
     - `Ảnh tham chiếu` (`referenceImage` cục bộ) — giữ nguyên.
     - `Phong cách hình ảnh` (`selectedStyle` / `visualStyles`) — giữ nguyên.
     - Nút `Yêu thích` (`toggleLike(item)`) trên từng kết quả — giữ nguyên state client.
   - **Luồng Tạo ảnh / Tạo lại (`regenerateImages` → `generateImages`):**
     - Validate `aiPromptText.trim()` không rỗng và có model hợp lệ (hoặc fallback default).
     - Gọi `mediaApi.generateAiImages({ prompt, description, aspect_ratio, ai_model_id, count })` → nhận `job_id`.
     - Polling `aiImageJobStatus(jobId)` theo chu kỳ 1.5s (dọn timer ở `onBeforeUnmount` và `closeDialog`/`resetAfterLeave`, chặn race condition nếu mở/đóng nhanh) cho đến khi `status === 'ready'` hoặc `'failed'`.
     - Render danh sách ảnh kết quả thật (`<img :src="item.preview_url" />`) kèm kích thước thật (`width × height`) và thông tin model thực tế đã chạy trong chế độ Grid & List. Nếu provider trả về `revised_prompt`, có thể hiển thị tooltip/nhãn phụ nhẹ ở chế độ List.
     - Khi lỗi (422 validation hoặc `AiGatewayException` từ provider/key): hiển thị `VAlert` màu `error` với message rõ ràng từ backend, giữ nguyên toàn bộ input của user để sửa và thử lại.
   - **Luồng `Lưu vào thư viện` (`saveToLibrary`):**
     - Disable nút với `:loading="isSaving"` khi đang gửi request.
     - Gọi `mediaApi.saveAiImage({ preview_id, folder: props.folder || 'root', title, alt_text })`.
     - Thành công: đánh dấu `item.savedAssetId = asset.id` (tránh bấm lưu trùng, hiển thị badge "Đã lưu"), hiện `VAlert` thành công, `emit('saved', asset)`.
   - **Luồng `Tải về ảnh đã chọn` (`downloadSelected` / `downloadItem`):**
     - Kích hoạt tải trình duyệt qua `item.download_url` (cờ `?download=1` có `Content-Disposition: attachment`).
3. **Cập nhật [index.vue](../Modules/Media/resources/js/pages/index.vue):**
   - Truyền `:folder="activeFolder || 'root'"` vào `<AiGenerateImageDialog>`.
   - Lắng nghe `@saved="onAiImageSaved"`: tái dùng `syncViewAfterUpload([asset])` + `store.loadFolders()` + `showNotice(...)` để ảnh AI vừa lưu xuất hiện ngay ở đầu cột giữa và chọn xem chi tiết ở `MediaDetailSidebar` (tab `"Prompt"` sẽ tự hiển thị metadata `ai_generation`).
   - Kiểm tra logic `syncViewAfterUpload` khi `activeQuickFilter === 'ai'` (nếu `asset.ai_generation` có dữ liệu thì không reset bộ lọc `'ai'`).

---

## 4. Kế hoạch kiểm thử & nghiệm thu (Verification Plan)

1. **Backend Feature & Unit Tests (`Modules/Media/tests/`):**
   - Test `GET /api/v1/admin/media/ai-image/models`: chỉ trả `AiModel` đang `is_enabled + is_available + provider active` và có `CAP_IMAGE_GENERATION`; kiểm tra trả `403` khi user thiếu quyền `media.manage`.
   - Test `POST /api/v1/admin/media/ai-image/generate` + `GET .../jobs/{jobId}` (mock `CoreAiGatewayContract` trả binary PNG hợp lệ, test với `count = 1` và `count = 4`):
     - Khẳng định `CoreAiGatewayContract::generateImage()` được gọi đúng số lần / tham số (`prompt`, `aspectRatio`, `providerKey`, `modelId`).
     - File tạm được ghi vào disk `temp_processing` (fake), signed URL `preview` trả về ảnh `200 image/png` và `403` nếu signature giả.
     - Người dùng khác không thể đọc `jobs/{jobId}` của người tạo (`403`/`404`).
     - Trường hợp `AiGatewayException` trả về trạng thái `failed` kèm thông báo lỗi rõ ràng.
   - Test `POST /api/v1/admin/media/ai-image/save`:
     - Lưu đúng vào `MediaAsset` thông qua `StoreGeneratedMediaAction`, ghi `custom_properties['ai_generation']` đầy đủ, dedupe khi bấm lưu lại cùng ảnh, trả `MediaAssetResource` đúng chuẩn.
   - Test `GET /api/v1/admin/media/assets?quick=ai`: lọc đúng các asset có metadata AI.
   - Chạy lệnh: `php artisan test Modules/Media/tests`
2. **Frontend Lint & Build:**
   - Chạy ESLint: `npx.cmd eslint -c .eslintrc.cjs Modules/Media/resources/js/services/mediaApi.js Modules/Media/resources/js/components/AiGenerateImageDialog.vue Modules/Media/resources/js/pages/index.vue`
   - Chạy build: `npm.cmd run build`
3. **Kiểm tra thủ công trên trình duyệt (sau khi implement):**
   - Mở trang Media → bấm **Tạo ảnh bằng AI** → kiểm tra dropdown `Model tạo ảnh` nạp đúng model từ `CoreAi`, các field `Mô tả hình ảnh`, `Gợi ý prompt`, `Ảnh tham chiếu`, `Phong cách hình ảnh`, `Yêu thích` vẫn hoạt động tĩnh bình thường.
   - Nhập `Prompt`, chọn `1:1` / `16:9`, chọn số lượng `2` hoặc `4` → bấm tạo → xem kết quả ảnh thật ở cả Grid & List → bấm **Lưu vào thư viện** và kiểm tra ảnh xuất hiện ngay trong lưới Media + tab `Prompt` ở `MediaDetailSidebar`.
