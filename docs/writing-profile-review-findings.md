# Rà soát luồng Writing Profile — Lỗi & Việc cần làm

> Ngày rà: 2026-11-09. Phạm vi: toàn bộ luồng WritingProfile (BE + FE + pipeline + prompt + queue).
> Trạng thái lúc rà: `php artisan test Modules/WritingStyle` = **80 passed (328 assertions)**.

Tài liệu này ghi lại các lỗi/điểm cần cải tiến đã rà được để xử lý tiếp. Mỗi mục ghi: hiện tượng, file liên quan, hướng sửa, mức độ.

---

## 🔴 Lỗi thật cần sửa

### L1. `store()` nuốt `tone_instructions` (Ô 3) khi POST — ĐANG SỬA (hướng B đã chốt)

**Hiện tượng:** `StoreWritingProfileRequest` có rule `tone_instructions`, nhưng `WritingProfileController::store()` không đưa nó vào `WritingProfile::create()` → POST kèm Ô 3 sẽ bị bỏ rơi (DB = NULL, response vẫn 201 "thành công"). `update()` (PUT) đã lưu được → POST/PUT không đối xứng.

**Vì sao chưa lộ:** trang create hiện không gửi `tone_instructions` (Ô 3 chỉ có trên edit page — user soạn tay, gợi ý từ 8 sliders Sắc thái). Vấn đề tiềm ẩn khi sau này thêm Ô 3 vào create hoặc API consumer khác gửi field này.

**File:** `Modules/WritingStyle/app/Http/Controllers/WritingProfileController.php` — mảng `create()` (dòng ~99-119).

**Sửa:** thêm `'tone_instructions' => $data['tone_instructions'] ?? ''` vào `create()`.

**Lưu ý — KHÔNG sửa `sandbox_samples_json`:** cột này cố ý không có trong request rules. Samples có endpoint riêng (`POST/DELETE .../sandbox-samples`) quản lý uuid + giới hạn 30 bản; cho CRUD ghi trực tiếp sẽ phá cấu trúc đó.

---

### L2. `tone_instructions` là field "mồ côi" trong analyzer schema — ĐANG SỬA (hướng B)

**Quyết định của user:** Ô 3 (`tone_instructions`) **không do AI tạo ra** — người dùng chỉnh tay theo mục đích viết bài. → **Hướng B: bỏ property khỏi `outputSchema()`**, không bắt AI sinh.

**Hiện tượng — field tồn tại ở đúng 1 chỗ rồi biến mất:**
- `outputSchema()` khai `'tone_instructions' => ['type' => 'string']` nhưng **không** nằm trong `required` → vi phạm chuẩn OpenAI strict `json_schema` (mọi property phải có trong `required`).
- `buildPrompt()` không có `tone_instructions_guide` → AI không được yêu cầu sinh.
- `analyze()` không đọc `$data['tone_instructions']`; `WritingAnalysisResultDto` không có field → `result_json` trong DB không bao giờ chứa nó.
- `store()` fallback `$analysisResult['tone_instructions']` → luôn null.

**File:** `Modules/WritingStyle/app/Services/WritingStyleAnalyzerService.php` (`outputSchema()`, dòng ~257).

**Sửa:** xóa dòng `'tone_instructions' => ['type' => 'string'],` khỏi `outputSchema()`.

---

### L3. Trang create còn 13 eslint error

**Hiện tượng:** chủ yếu `vue/html-comment-indent` đầu file + `padding-line-between-statements` + `lines-around-comment`. Không ảnh hưởng runtime nhưng làm CI lint bẩn. `eslint --fix` chữa gần hết.

**File:** `Modules/WritingStyle/resources/js/pages/writing-profiles/create/index.vue`.

---

### L4. `edit` page: `buildRulesJsonPayload` tách `structure_patterns`/`transitions` bằng `' • '` có thể lệch dữ liệu — ĐÃ SỬA

**Hiện tượng:** [edit/[id].vue ~1148-1159] — section 2 của Tab 3 gộp 2 khóa thành 1 textarea bằng `' • '` rồi tách ngược lúc lưu. 4 kịch bản hỏng dữ liệu:
- Rule gốc chứa `'•'` (AI đánh dấu đầu dòng) → phần của `structure_patterns` trôi sang `transitions`.
- Chỉ có `transitions`, thiếu `structure_patterns` → giá trị transitions được ghi nhầm vào key `structure_patterns`.
- Tier `micro`/`short`: chữ fallback "Không áp dụng..." (placeholder hiển thị) bị ghi vào DB như rule thật; nếu sau này đổi `dominant_tier` lên `long` sẽ trôi vào prompt.
- Xóa ô trống khi rule thiếu → ghi `Chưa có dữ liệu.` vào DB.

**Đã sửa (edit/[id].vue):**
- Tách thành **4 section riêng** map 1:1 4 khóa `opening` / `structure_patterns` / `transitions` / `ending` — không còn gộp/tách bằng separator.
- Placeholder tier-aware ("Không áp dụng...", "Chưa có dữ liệu") chuyển sang prop `placeholder` của textarea — không bao giờ thành giá trị lưu DB.
- `buildRulesJsonPayload` ghi WYSIWYG theo thứ tự 4 section; rỗng → `''` (ConvertEmptyStringsToNull phía BE biến thành null → xóa khóa, validation nullable vẫn pass).
- Template Tab 3 chuyển grid 3 cột → 2×2 (`md="6"`) cho 4 ô.

---

## 🟡 Chưa hoàn thiện / cần nối

### L5. `writingStyleApi` thiếu `runSandboxStep()` — thuộc Task 5 (user tự làm)

**Hiện tượng:** endpoint `POST .../sandbox/steps/{step}` đã sẵn sàng (BE pipeline đã xong — 80 test xanh) nhưng FE chưa có `writingStyleApi.runSandboxStep` + store `tierPipelines` getter + `runSandboxStep` action + `StepTopicOutlineEditor.vue` + stepper Tab 9 trong `edit/[id].vue`.

**Trạng thái:** user đã nhận tự làm ("số 5 tôi sẽ làm sau") — KHÔNG sửa trong đợt này.

---

### L6. `edit` page sandbox vẫn gọi `store.sandbox` (1 bước) thay vì pipeline `runStep`

**Hiện tượng:** tier `long` trên UI edit hiện chạy 1 bước `article_writer`, chưa qua `topic_outline`. Đúng chỗ Task 5 sẽ lắp stepper — giải quyết cùng L5.

---

### L7. `AnalyzeWritingProfileJob::handle()` — docblock của `failed()` dính vào `}` đóng của `handle()`

**Hiện tượng:** dòng ~99 `}    /**` — syntax hợp lệ nhưng xấu, thiếu xuống dòng.

**File:** `Modules/WritingStyle/app/Jobs/AnalyzeWritingProfileJob.php`.

**Sửa:** xuống dòng trước docblock.

---

## 🔵 Đề xuất cải tiến (không bắt buộc)

### I1. `store()`/`update()` đồng nhất mảng field

`store()` nên gán `tone_instructions` (L1). Nếu muốn cho phép seed `sandbox_samples_json` qua CRUD thì cân nhắc thêm — nhưng hiện cố ý không cho (xem L1).

### I2. Scheduled cleanup orphan `SystemTaskRun`

Các taskRun `queued`/`running` > N phút mà bảng `jobs` không còn row tương ứng → `fail`. Đã từng cleanup tay trong smoke test; nên có job định kỳ.

### I3. Thống nhất tier labels từ `options.tiers`

`config('writingstyle.tiers.*.label')` đã trả qua `GET /options`; kiểm tra FE list/create có hardcode label tier không → nên lấy từ `options` duy nhất.

### I4. `countSentences` regex chưa xử lý `:`/`;`

`/[.!?…]+|\n{2,}|—\s*/` — text nhiều dấu chấm phẩy/two-chấm có thể lệch tier `micro`/`short`. Chấp nhận được cho phân tầng; cân nhắc nếu thấy phân loại sai thực tế.

---

## Trạng thái xử lý

- [x] L1 — ĐÃ SỬA: `store()` gán `tone_instructions` vào `create()` (POST/PUT đối xứng).
- [x] L2 — ĐÃ SỬA theo hướng B: xóa `tone_instructions` khỏi `outputSchema()` của analyzer.
- [x] L3 — ĐÃ SỬA: `eslint --fix` dọn hết 13 error của create page (0 error còn lại).
- [x] L7 — ĐÃ SỬA: xuống dòng docblock `failed()` trong `AnalyzeWritingProfileJob`.
- [x] L4 — ĐÃ SỬA: tách 4 section riêng 1:1 4 khóa, placeholder chuyển sang prop, bỏ split `' • '`.
- [ ] L5 + L6 — Task 5 (user tự làm): `runSandboxStep` API + store + stepper Tab 9.
- [ ] I1-I4 — đề xuất, chưa quyết.

Sau khi sửa L1+L2+L3+L7: `php artisan test Modules/WritingStyle` vẫn **80 passed (328 assertions)**.
