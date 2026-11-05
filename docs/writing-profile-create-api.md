# Kế hoạch Nối API: Trang `writing-profiles/create`

> **File đích:** `Modules/WritingStyle/resources/js/pages/writing-profiles/create/index.vue` (2875 dòng)
> **Trạng thái hiện tại:** GĐ1 (options) + GĐ2 (endpoint đọc analysis) + GĐ3 (giải mã paste) + GĐ4 (lưu profile & sandbox) **đã nối API**.
> **Tiến độ nối API:** 4 / 6 giai đoạn.

---

## 1. Hiện trạng

### 1.1. Backend đã có (P2-02 Task 1–9 xanh)

| Method | Endpoint | Permission | Trả về |
| :--- | :--- | :--- | :--- |
| `GET` | `/v1/admin/writing-style/writing-profiles` | `writing.profile.view` | dataTable `{items, itemsLength}` |
| `GET` | `/v1/admin/writing-style/writing-profiles/options` | `writing.profile.view` | `{tiers[], rules[]}` |
| `GET` | `/v1/admin/writing-style/writing-profiles/{id}` | `writing.profile.view` | `WritingProfileResource` |
| `POST` | `/v1/admin/writing-style/writing-profiles` | `writing.profile.create` | 201 + `WritingProfileResource` |
| `PUT` | `/v1/admin/writing-style/writing-profiles/{id}` | `writing.profile.create` | 200 + `WritingProfileResource` |
| `DELETE` | `/v1/admin/writing-style/writing-profiles/{id}` | `writing.profile.manage` | 204 |
| `POST` | `/v1/admin/writing-style/writing-profiles/analyze` | `writing.evaluate` | **202** `{analysis_id, task_run_id, status}` |
| `POST` | `/v1/admin/writing-style/writing-profiles/{id}/sandbox` | `writing.evaluate` | 200 `SandboxResultDto` |

### 1.2. Backend CHƯA có (chặn một phần trang create)

1. **Không có endpoint đọc một analysis theo id.** `routes/api.php` của WritingStyle không có route nào trỏ tới `WritingProfileAnalysis`.
2. **Không có `GET /tasks/{id}`** trong TaskCenter — chỉ có `index`, `active`, `adapters`, `store`, `cancel`.
3. **`GET /tasks/active` chỉ trả `queued` + `running`** (`SystemTaskRun::scopeActive`, [SystemTaskRun.php:81](../Modules/TaskCenter/app/Models/SystemTaskRun.php:81)) và giới hạn 30 bản ghi. Nghĩa là **khi job xong, task biến mất khỏi danh sách** — FE poll `/active` sẽ thấy task mất tích mà không bao giờ đọc được `meta.profile_id`. Đây là lỗ hổng chặn luồng async, phải xử lý ở Giai đoạn 2.
4. **Không có fetcher URL và parser file.** [WritingAnalyzeController:38](../Modules/WritingStyle/app/Http/Controllers/WritingAnalyzeController.php:38) trả 422 thẳng nếu `reference_text` rỗng, kèm thông báo *"Hiện chỉ hỗ trợ phân tích trực tiếp (paste). Nguồn url/file sẽ bổ sung fetcher sau."*
5. **`StoreWritingProfileRequest` không nhận `taxonomy`** dù model `WritingProfile` có trait `HasTaxonomy` scope `writing_profile`.

### 1.3. Tiền lệ nội bộ

| File | Đã nối API | Ghi chú |
| :--- | :--- | :--- |
| `pages/.../list/index.vue` | **Có** | Tiền lệ duy nhất. `onMounted → store.fetchProfiles()`, computed map từ `store.profiles`, `onAnalyze` gọi `store.analyze()` nhưng **comment thừa nhận chưa poll task** ([list/index.vue:503](../Modules/WritingStyle/resources/js/pages/writing-profiles/list/index.vue:503)) |
| `pages/.../edit/[id].vue` | Không | Header ghi *"chưa gọi API (demo tĩnh)"*, dữ liệu là brand cà phê không liên quan |
| `pages/.../create/index.vue` | Không | File này |

**Quy ước phải tuân theo:**
- `$api` ([utils/api.js](../resources/js/utils/api.js)) tự unwrap `BaseResponse.data`, tự xử lý 401, chuẩn hóa lỗi thành `ApiError` có `.status`/`.errors`/`.isValidation`. Dùng `firstErrorMessage(e)` và `fieldErrors(e)` khi catch.
- Store Pinia pattern load-once: cờ `*Loaded` + `*loading`, cập nhật mảng in-place thay vì refetch.
- Không có composable snackbar dùng chung — trang tự giữ `snack` reactive (giữ nguyên cách hiện tại).
- Route name sinh từ file-based routing: `admin-writing-style-writing-profiles-list` / `-create` / `-edit-id`.

### 1.4. Phân loại 60 khối dữ liệu trong trang create

| Nhóm | Số khối | Nghĩa |
| :--- | :--- | :--- |
| `co_backend` | 13 | Backend cấp đủ dữ liệu — **nối được ngay** |
| `thieu_backend` | 19 | UI có nhưng backend không có field/endpoint nào cấp được |
| `thuan_ui` | 28 | Label, option, tiện ích hiển thị — không cần API |

**13 khối `co_backend` (mục tiêu nối):**

| Dòng | Khối | Nguồn backend |
| :--- | :--- | :--- |
| 145 | `wordCount` | `analysis_metadata_json.word_count` |
| 155 | `sentenceCount` | `analysis_metadata_json.sentence_count` |
| 164 | `detectedTier` | `analysis_metadata_json.detected_tier` |
| 227 | `DNA_RULE_EXTRACTIONS` | `rules_json` (16 khóa) |
| 311 | `droppedRuleKeys` | logic `WritingProfile::applicableRules()` |
| 322 | `filteredDnaRules` | dẫn xuất từ `rules_json` |
| 329 | `DNA_EVIDENCES` | `evidence_json[{rule_key,excerpt,note}]` |
| 674 | `computedStyleInstructions` | `style_instructions` |
| 728 | `profileForm` | các field `writing_profiles` |
| 829 | `runAnalysis()` | `POST /analyze` |
| 846 | `fetchFromUrl()` | `POST /analyze` (`source_type=url`) — cần BE mới |
| 954 | `runSandboxDemo()` | `POST /{id}/sandbox` |
| 977 | `saveWritingProfile()` | `POST /writing-profiles` |

**Phát hiện đáng chú ý:** `detectedTier` client ([create/index.vue:164](../Modules/WritingStyle/resources/js/pages/writing-profiles/create/index.vue:164)) khớp `WritingProfileDefinition::detectTier()` gần như 100%, chỉ khác regex tách câu — backend có thêm em-dash `—\s*` trong `preg_split`. Nên giữ computed client cho phản hồi realtime, nhưng sau khi analyze xong thì **lấy tier của backend làm chuẩn** để tránh lệch.

---

## 2. Danh sách khoảng cách (Gap)

| ID | Phía | Tiêu đề | Phụ thuộc | Công |
| :--- | :--- | :--- | :--- | :--- |
| `G1` | FE | Store + service chưa có action đọc analysis / theo dõi task | — | S |
| `G2` | **BE** | Thiếu `GET /writing-profiles/analyses/{id}` — FE không đọc được kết quả job | — | S |
| `G3` | FE | `runAnalysis()` là hàm giả, không gọi `POST /analyze` | `G1`,`G2` | M |
| `G4` | FE | Không có cơ chế chờ job async (poll + timeout + hủy khi rời trang) | `G2` | M |
| `G5` | FE | 16 rule card, evidence, style_instructions đọc từ hằng số thay vì kết quả API | `G3` | M |
| `G6` | FE | `saveWritingProfile()` giả, điều hướng id cứng `id: 1` | `G3` | M |
| `G7` | FE | `runSandboxDemo()` giả; sandbox cần `profile_id` mà trang create chưa có | `G6` | M |
| `G8` | **BE** | Thiếu fetcher URL (`source_type=url`) | — | L |
| `G9` | **BE** | Thiếu parser file (`source_type=file`) | — | M |
| `G10` | FE | `fetchFromUrl()` nạp HTML mẫu thay vì gọi BE | `G8` | S |
| `G11` | **BE** | `StoreWritingProfileRequest` không nhận taxonomy | — | S |
| `G12` | FE+BE | 19 khối UI không có backend nào cấp được | — | L |

---

## 3. Sáu giai đoạn nối API

Mỗi giai đoạn tự chạy được và kiểm chứng được độc lập. Thứ tự ưu tiên: dùng backend sẵn có trước (giá trị ngay, rủi ro thấp), rồi mới mở rộng backend.

---

### Giai đoạn 1 — Nền móng: store + options + đọc profile

**Mục tiêu:** trang create nạp được `tiers` và 16 `rule_keys` thật từ API, thay vì hằng số `TIER_OPTIONS` và tiêu đề rule hardcode.

**Gap:** `G1` (một phần)

**Việc cần làm**

1. `services/writingStyleApi.js` — thêm:
   ```js
   analysis(id) { return $api(`${BASE}/writing-profiles/analyses/${id}`) }
   ```
   (dùng được sau Giai đoạn 2; khai báo trước để một chỗ quản endpoint)

2. `stores/writingStyle.js` — thêm state và action:
   - `analysisResult: null` — kết quả analyze gần nhất đã đọc xong
   - `analysisPolling: false`
   - `fetchAnalysis(id)` — gọi `writingStyleApi.analysis(id)`

3. `create/index.vue`:
   - `import { useWritingStyleStore }` + `onMounted(() => store.fetchOptions())`
   - `TIER_OPTIONS` → computed từ `store.tiers`, giữ fallback hằng số khi API chưa trả (tránh trắng UI)
   - Tiêu đề 16 rule card lấy `question` từ `store.ruleKeys` thay vì chuỗi cứng trong `DNA_RULE_EXTRACTIONS[].title`

**Kiểm chứng**
- Mở trang → Network thấy `GET /writing-profiles/options` 200
- Select "Cấp độ độ dài" vẫn đủ 4 lựa chọn, nhãn khớp `sentence_hint` từ config
- Tắt backend → trang vẫn render bằng fallback, không trắng

---

### Giai đoạn 2 — Backend: endpoint đọc analysis

**Mục tiêu:** FE đọc được trạng thái và kết quả của một lần giải mã. Đây là điều kiện chặn cho mọi giai đoạn sau.

**Gap:** `G2`

> **Vì sao bắt buộc:** `GET /tasks/active` chỉ trả `queued`+`running`. Job xong → task rời danh sách → FE không bao giờ thấy `meta.profile_id`. Poll `GET /tasks` (index) cũng không cứu được: nó trả raw model nên key là `meta_json` chứ không phải `meta`, và không có `ui`. Thêm một endpoint đọc analysis theo id là cách gọn và đúng tầng nhất — FE chỉ cần một id duy nhất đã nhận từ response 202.

**Việc cần làm**

1. `app/Http/Controllers/WritingAnalyzeController.php` — thêm `show()`:
   ```php
   public function show(string $id)
   {
       $analysis = WritingProfileAnalysis::with('draftProfile')->findOrFail($id);
       return BaseResponse::success(new WritingAnalysisResource($analysis));
   }
   ```

2. Tạo `app/Http/Resources/WritingAnalysisResource.php` trả về:
   ```
   id, name, source_type, source_url, status, detected_tier,
   ai_run_id, error_code, error_message, created_at,
   draft_profile_id,
   result_json       → rules_json, evidence_json, style_instructions, metadata
   draft_profile     → WritingProfileResource (khi đã succeeded)
   ```
   **Không** trả `reference_text` — văn bản mẫu đã có sẵn ở FE, trả lại chỉ làm phình payload và lộ nội dung không cần thiết.

3. `routes/api.php` — khai báo **trước** `apiResource` để không bị route model binding nuốt:
   ```php
   Route::get('writing-profiles/analyses/{analysis}', [WritingAnalyzeController::class, 'show'])
       ->middleware('permission:writing.profile.view')
       ->name('writing-profiles.analyses.show');
   ```

4. Test `Modules/WritingStyle/tests/Feature/` — `GET` analysis `pending` → `status=pending`; analysis `succeeded` → có `draft_profile` kèm `rules_json` 16 khóa.

**Kiểm chứng**
- `php artisan route:list --name=writing-style` thấy route mới
- `php artisan test --filter=WritingAnalysis` xanh
- `curl` một analysis_id thật → đúng status, đúng `draft_profile_id`

---

### Giai đoạn 3 — Nối luồng giải mã (paste)

**Mục tiêu:** bấm "Phân tích với AI" chạy giải mã thật trên nội dung người dùng dán, chờ job xong, và điền 16 rule card + evidence + style_instructions bằng dữ liệu AI trả về.

**Gap:** `G3`, `G4`, `G5`

**Việc cần làm**

1. `stores/writingStyle.js` — thêm action chờ job:
   ```js
   async waitForAnalysis(id, { intervalMs = 3000, timeoutMs = 300000 } = {}) {
     // poll fetchAnalysis(id) đến khi status ∈ {succeeded, failed}
     // hoặc quá timeoutMs (khớp timeout 300s của job ai-text)
     // trả { status, analysis }
   }
   ```
   Lưu handle timer vào state để `stopPolling()` được khi người dùng rời trang.

2. `create/index.vue` — viết lại `runAnalysis()`:
   ```
   validate articleContent (bỏ tag HTML, >= 50 ký tự theo AnalyzeTextRequest)
   → analyzing = true
   → store.analyze({ source_type: 'paste', reference_text: plainText, name: profileForm.name })
   → nhận { analysis_id, task_run_id }
   → store.waitForAnalysis(analysis_id)
   → succeeded: map kết quả vào state; failed: snackbar đỏ kèm error_message
   → analyzing = false
   ```

3. Chuyển 3 khối hằng số sang state động — giữ hằng số làm giá trị khởi tạo để UI không trống trước khi phân tích:
   - `DNA_RULE_EXTRACTIONS` → `ref(...)`, nạp từ `result_json.rules_json`. Map `key → {title (từ options), desc (giá trị AI trả)}`. Riêng `uncertainties` là **mảng** chuỗi, 15 khóa còn lại là **chuỗi** — phải xử lý riêng, không `trim()` thẳng.
   - `DNA_EVIDENCES` → `ref([])`, nạp từ `result_json.evidence_json`, đổi `rule_key` → `ruleKey` cho khớp template hiện tại.
   - `STYLE_INSTRUCTIONS_BASE` → `ref('')`, nạp từ `result_json.style_instructions`. `computedStyleInstructions` vẫn nối thêm mục "6. Hiệu chỉnh sắc thái cảm xúc" từ `toneSliders` (phần UI-only, xem Giai đoạn 6).
   - Sau khi xong: `selectedDnaTierFilter = analysis.detected_tier` (lấy tier backend làm chuẩn), đồng bộ `profileForm.dominantTier`.

4. Trạng thái tải: thêm `VProgressLinear` indeterminate hoặc skeleton trên Card 2 trong lúc `analyzing`; vô hiệu hóa nút để tránh bấm hai lần (backend đã dedupe theo `source_hash` nhưng UI vẫn nên chặn).

5. Hủy đúng lúc: `onBeforeUnmount(() => store.stopPolling())`.

**Kiểm chứng**
- Dán bài 300 từ → bấm "Phân tích với AI" → Network: `POST /analyze` 202, rồi các lần `GET /analyses/{id}` 3s/lần
- Queue worker chạy (`php artisan queue:work --queue=ai-text`) → sau 10–60s tab "Văn phong" hiện 16 rule **của bài vừa dán**, không còn nội dung Laravel 11 mẫu
- Evidence hiện trích dẫn có thật trong bài đã dán (đối chiếu mắt)
- Tắt worker → sau 5 phút snackbar báo timeout, không treo vô hạn
- Dán 20 ký tự → snackbar 422 "tối thiểu 50 ký tự", không gọi API

---

### Giai đoạn 4 — Lưu profile & Sandbox

**Mục tiêu:** nút "Lưu Writing Profile" tạo bản ghi thật và điều hướng bằng id thật; "Phòng thử DNA" viết thử bằng profile vừa tạo.

**Gap:** `G6`, `G7`

**Việc cần làm**

1. Viết lại `saveWritingProfile(openEditor)`:
   - **Hai nhánh, phân biệt rõ:**
     - Đã chạy analyze xong → đã có `draft_profile_id` (job tự tạo draft trong [WritingStyleAnalyzerService::createDraft](../Modules/WritingStyle/app/Services/WritingStyleAnalyzerService.php:160)). Dùng `PUT /writing-profiles/{draft_profile_id}` để cập nhật tên/mô tả/status người dùng sửa — **không `POST`**, nếu không sẽ sinh profile trùng cho cùng một lần giải mã.
     - Chưa analyze → `POST /writing-profiles` tạo mới (`origin=manual`).
   - Map camelCase → snake_case: `dominantTier→dominant_tier`, `isEnabled→is_enabled`.
   - `profileForm.slug` và `profileForm.taxonomyTags` hiện **không có trong `StoreWritingProfileRequest`** — bỏ khỏi payload (gửi lên sẽ bị validate loại bỏ im lặng), hoặc chờ `G11`.
   - Điều hướng bằng id thật: `router.push({ name: '...-edit-id', params: { id: res.id } })`.

2. Viết lại `runSandboxDemo()`:
   - Sandbox cần `profile_id`. Trang create chưa lưu thì chưa có id → **chặn nút** kèm tooltip *"Hãy phân tích bài mẫu trước"* khi chưa có `draft_profile_id`.
   - Gọi `store.sandbox(profileId, { topic, target_tier: sandboxForm.targetTier })`, hiện `res.text`.
   - `SANDBOX_SAMPLES_BY_TIER` chuyển thành giá trị mặc định lúc chưa chạy; sau khi chạy hiện text thật.
   - Sandbox là lệnh gọi AI đồng bộ (`temperature 0.7`, không qua queue) → có thể mất 5–20s. Phải có spinner trên nút.

3. Hiển thị snapshot: `res.snapshot.system_prompt` chính là bản prompt đã strip evidence. Đưa vào Card "Gợi ý prompt" chế độ `dna` — đây là **bằng chứng chống đạo văn** cho người quản trị thấy AI nhận gì.

**Kiểm chứng**
- Phân tích → sửa tên → "Tạo Writing Profile" → Network `PUT` (không phải `POST`), DB chỉ có **một** profile cho lần giải mã đó
- Điều hướng sang `/edit/{id}` với id thật, không phải `1`
- Trang list hiện profile mới sau khi refresh
- Sandbox: nhập chủ đề → ra văn bản mới về chủ đề đó, giọng khớp bài mẫu, **không chứa câu nào copy nguyên văn** từ bài mẫu
- Chưa analyze → nút sandbox bị khóa, không gọi API lỗi 404

---

### Giai đoạn 5 — Backend: nguồn URL và file

**Mục tiêu:** hai tab "Từ URL" và "Từ file" chạy thật, không còn nạp HTML mẫu.

**Gap:** `G8`, `G9`, `G10`

> Tách riêng vì cần code backend mới và có mặt an ninh: fetch URL do người dùng nhập là **SSRF**. Phải chặn địa chỉ nội bộ (`127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16`, `::1`, metadata endpoint cloud `169.254.169.254`), chỉ cho `http`/`https`, giới hạn redirect, timeout, và **giới hạn kích thước phản hồi** để không bị bom nội dung.

**Việc cần làm**

1. `app/Services/ReferenceSourceFetcher.php` (mới):
   - `fetchFromUrl(string $url): string` — Guzzle/Http với allowlist scheme, kiểm tra IP đích sau khi resolve DNS (chặn cả rebinding), `timeout 15s`, `max_redirects 3`, cắt body ở ~2MB; bóc nội dung chính bằng `symfony/css-selector` + `DomCrawler` (đã có trong `vendor`); strip `script`/`style`/`nav`/`footer`.
   - `fetchFromFile(UploadedFile $file): string` — nhận `.txt`/`.md`/`.html`, giới hạn 2MB, MIME-check thật (không tin đuôi file), `strip_tags` cho html.
   - Trả lỗi nghiệp vụ rõ ràng: không với được, nội dung quá ngắn (`< 50` ký tự theo `AnalyzeTextRequest`), kiểu file không hỗ trợ.

2. `AnalyzeTextRequest` — thêm `file` (`nullable`, `file`, `mimes:txt,md,html`, `max:2048`) và `required_if:source_type,file`.

3. `WritingAnalyzeController::store()` — thay nhánh 422 hiện tại:
   ```
   paste → reference_text
   url   → fetcher->fetchFromUrl($data['source_url'])
   file  → fetcher->fetchFromFile($request->file('file'))
   ```
   Giữ nguyên `dispatchAnalysis()` phía sau — dedupe theo `source_hash` vẫn hoạt động vì text đã được bóc xong trước khi hash.

4. Trả thêm metadata preview cho FE (`URL_PREVIEW_META` đang là giả): `{domain, title, word_count}` lấy từ `<title>` và đếm từ.

5. FE `fetchFromUrl()` — gọi `POST /analyze` với `source_type: 'url'`, nạp `URL_PREVIEW_META` từ response, rồi đi tiếp luồng `waitForAnalysis` của Giai đoạn 3. Tab file dùng `FormData` (không phải JSON) vì có upload.

6. Test: URL nội bộ → bị chặn 422; URL hợp lệ → ra text; file `.md` → ra text; file `.exe` đổi đuôi `.txt` → bị chặn.

**Kiểm chứng**
- Dán URL bài báo thật → nội dung thật hiện trong editor, tiêu đề/domain đúng
- Nhập `http://127.0.0.1:3306` → 422 chặn SSRF, không có request nào thoát ra
- Tải file `.md` 50KB → ra nội dung, phân tích chạy tiếp
- `php artisan test --filter=ReferenceSourceFetcher` xanh

---

### Giai đoạn 6 — Xử lý 19 khối không có backend

**Mục tiêu:** trang không còn chỗ nào hiển thị số liệu giả như thật.

**Gap:** `G12`, `G11`

> **Đây là vấn đề thật, không phải việc dọn dẹp.** 19 khối đang hiện số đo cụ thể (`88%` câu chủ động, `82/100` đọc lướt, `84` độ độc đáo, `16.4 từ/câu`) mà không hệ thống nào tính ra. Người dùng không có cách nào biết đó là số giả. Để nguyên là vừa gây hiểu sai vừa nợ kỹ thuật.

**Phân loại và khuyến nghị**

| Nhóm UI | Khối | Khuyến nghị | Lý do |
| :--- | :--- | :--- | :--- |
| Tab Nội dung | `ANALYSIS_SUMMARY`, `TOPICS`, `STRENGTHS`, `IMPROVEMENTS` | **Ẩn tab** (`isExperiment` đã có sẵn) | Không nằm trong spec PLAN.md §298–311. Thuộc hướng "chấm điểm" của spec cũ `09-writing-style.md` đã bị thay thế |
| Tab Hình ảnh | `IMAGE_STATS`, `DETECTED_IMAGES`, `SUGGESTED_IMAGE_SLOTS` | **Ẩn tab** | Phân tích ảnh là việc của `Modules/VisualStyle` (P2-03), không phải DNA văn phong |
| Tab Tính độc đáo | `UNIQUENESS_METRICS`, `REWRITE_COMPARISONS` | **Ẩn tab** | Cần engine so khớp ngữ liệu, chưa có kế hoạch nào trong PLAN.md |
| Tab Cấu trúc | `STRUCTURE_METRICS`, `STRUCTURE_FLOW`, `HEADING_OUTLINE` | **Tính client-side** từ HTML editor | Đếm H1–H3, số câu/đoạn, từ/câu đều tính được bằng JS trên nội dung đang có. Không cần backend. Là số thật, giữ được giá trị cho người dùng |
| Tab Văn phong | `STYLE_DNA_METRICS` (3/4 chỉ số), `STYLE_RULE_CHECKS` | **Bỏ**, giữ lại chỉ số Tier | `archetype`, `active voice %`, `pronouns nhất quán %` không có trong 16 khóa DNA. `STYLE_RULE_CHECKS` dùng 16 quy tắc của spec cũ (`no_corporate_cliche`...) — hệ thống hiện tại không có bảng `writing_rules` |
| Card phối âm cảm xúc | `toneSliders` (8 thanh), `TONE_PRESETS` | **Giữ**, đổi thành công cụ soạn `style_instructions` | Đây là input người dùng, không phải số liệu giả. Đang được nối vào `computedStyleInstructions` → lưu được vào `style_instructions`. Chỉ cần nói rõ trên UI đây là tinh chỉnh thủ công, không phải kết quả AI đo |
| Card prompt | `PROMPT_TEXT` (prompt viết lại bài) | **Bỏ** | Trang này tạo DNA văn phong, không viết lại bài. Prompt viết bài thuộc `Blog` (P3-02) |
| Form | `profileForm.taxonomyTags` | **Nối** sau khi làm `G11` | `WritingProfile` có `HasTaxonomy` scope `writing_profile`, chỉ thiếu field trong FormRequest |

**Việc cần làm cho `G11`:** thêm `taxonomy_ids` (`nullable`, `array`) vào `StoreWritingProfileRequest`, và `syncTaxonomies()` trong `WritingProfileController::store/update`.

**Kiểm chứng**
- Grep trang create không còn hằng số nào chứa số đo giả
- Ba tab thí nghiệm không hiện trên UI (hoặc hiện kèm nhãn rõ ràng là chưa có dữ liệu)
- Tab Cấu trúc hiện số đếm **đúng** với nội dung đang dán (đối chiếu tay)
- Gắn 2 taxonomy → lưu → mở lại trang edit thấy đủ

---

## 4. Thứ tự thực thi và phụ thuộc

```
G1 ─────────────┐
                ├──► GĐ1 (nền móng)
                │
G2 ──► GĐ2 (BE: đọc analysis)  ◄── CHẶN mọi thứ phía sau
                │
                ├──► GĐ3 (giải mã paste)  ──► G3,G4,G5
                │         │
                │         └──► GĐ4 (lưu + sandbox)  ──► G6,G7
                │
G8,G9 ──► GĐ5 (BE: url + file)  ──► G10     (chạy song song được với GĐ3-4)
                │
G11,G12 ──► GĐ6 (dọn khối không backend)   (độc lập, làm lúc nào cũng được)
```

**Đường găng:** GĐ2 → GĐ3 → GĐ4. Làm đúng thứ tự này là có một luồng end-to-end chạy được: dán bài → giải mã thật → lưu profile thật → viết thử thật.

**Chạy song song được:** GĐ5 (backend URL/file) không phụ thuộc GĐ3-4. GĐ6 hoàn toàn độc lập.

---

## 5. Rủi ro

| Rủi ro | Ảnh hưởng | Giảm thiểu |
| :--- | :--- | :--- |
| Job `ai-text` cần worker chạy | Không có worker thì analyze treo mãi ở `pending`, người dùng tưởng hỏng | `waitForAnalysis` có timeout 300s khớp `$timeout` của job; snackbar nói rõ "cần queue worker" |
| Dedupe `source_hash` gây bối rối | Dán lại cùng nội dung khi job trước đang chạy → trả về analysis cũ, người dùng tưởng không chạy | Nhận biết `analysis_id` trùng với lần trước → snackbar *"Đang giải mã nội dung này rồi"* |
| Analyze tự tạo draft profile | `POST` thêm lần nữa khi lưu sẽ sinh profile trùng | GĐ4 phân nhánh `PUT` khi đã có `draft_profile_id` |
| `uncertainties` là mảng, 15 khóa kia là chuỗi | Render `[object Object]` hoặc crash | Xử lý riêng khi map `rules_json` |
| Tier client lệch tier backend | Chip hiện `medium` nhưng DNA giải mã theo `short` | Sau analyze lấy `detected_tier` của backend làm chuẩn |
| SSRF ở fetcher URL | Đọc được dịch vụ nội bộ, metadata cloud | GĐ5 bắt buộc chặn IP nội bộ sau resolve DNS + giới hạn redirect/size/timeout |
| Trang 2823 dòng | Sửa trực tiếp dễ vỡ, khó review | Mỗi giai đoạn một commit riêng; cân nhắc tách `useWritingProfileAnalysis()` composable cho logic analyze+poll |
| Payload `rules_json` lớn | 16 khóa + evidence có thể vài chục KB | Chấp nhận được; `style_instructions` đã giới hạn 10000 ký tự |

---

## 6. Ghi chú về phạm vi

Trang create hiện trộn hai bản spec khác nhau:

- **PLAN.md §298–311 + P2-02** (đang thực thi): 16 khóa DNA là *câu hỏi giải mã* về bài mẫu, 4 cấp độ độ dài, kiểm chứng trích dẫn, snapshot strip evidence. Không có điểm số.
- **docs/modules/09-writing-style.md** (cũ): 16 *quy tắc* hệ thống áp xuống, `overall_score` 0–100, `violations`, gauge bán nguyệt, `archetype`.

Code backend theo spec mới. UI trang create lại có cả gauge điểm, `STYLE_RULE_CHECKS` với code quy tắc của spec cũ, và `archetype` — đó là nguồn gốc của 19 khối `thieu_backend`. Giai đoạn 6 giải quyết việc này theo hướng **giữ spec mới**, vì đó là spec backend đã triển khai và PLAN.md gọi rõ cách tiếp cận mới là *"Giải mã Yếu tố thay vì chấm điểm cứng nhắc"*.

Nếu muốn giữ phần chấm điểm, đó là một tính năng mới cần spec riêng (bảng `writing_rules`, service chấm điểm, endpoint `evaluate`) — không nằm trong P2-02.
