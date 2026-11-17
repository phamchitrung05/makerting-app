# PLAN — Sửa 15 lỗi luồng Writing Profile (code-review xhigh)

> Ngày lập: 2026-11-17  
> Phạm vi: module `Modules/WritingStyle` (backend + frontend) — luồng create/analyze/poll → lưu profile → edit → Sandbox Pipeline  
> Nguồn lỗi: báo cáo `/code-review luồng writing profile` xhigh (10 góc tìm kiếm + dedup + sweep), 15 findings  
> Tiêu chí duyệt: mỗi lỗi chỉ được đánh dấu xong khi (a) code được sửa, (b) test cần thiết được viết/cập nhật, (c) chạy `php artisan test Modules/WritingStyle` xanh và smoke-test FE các trang create/edit/list.

## Quy ước đọc file

- `[f]` = file sửa, `[t]` = file test, `[m]` = migration/command mới.
- Thứ tự thực thi theo lô (Batch) — lô sau có thể phụ thuộc lô trước về file chung.
- Số thứ tự lỗi (#1…#15) khớp thứ tự findings trong báo cáo review (xem mục "Danh sách lỗi").

## Danh sách lỗi (tóm tắt 15 findings)

| # | Mức | File | Lỗi |
|---|-----|------|-----|
| 1 | Cao | `resources/js/pages/writing-profiles/edit/[id].vue:2070` | `saveProfile()` ghi cứng `status` theo toggle is_enabled — profile draft+enabled bị đổi thành active sau lần sửa đầu |
| 2 | Cao | `resources/js/pages/writing-profiles/edit/[id].vue:1374` | `refreshSandboxOutline()` là dead code (không có nút gọi) + fingerprint gồm `profile_version` → dàn ý đang dở 422 "Dàn ý đã cũ" không thoát được |
| 3 | Cao | `resources/js/pages/writing-profiles/edit/[id].vue:1316` | `outline_approved` luôn auto-true — gate duyệt dàn ý của backend bị FE bypass |
| 4 | Cao | `app/Http/Controllers/WritingProfileController.php:178` | `store()` không check ownership/re-link của `analysis_id` — profile người khác có thể bị gắn result; re-link đè `draft_profile_id` cũ |
| 5 | Cao | `app/DTOs/WritingBrief.php:250` | Check `missing_fields` của `required_details` là dead code — chi tiết luôn nằm trong chính "corpus" đối chiếu |
| 6 | Trung | `resources/js/pages/writing-profiles/edit/[id].vue:2133` + `create/index.vue:419` | Blur ô Tên đè slug user tự gõ; trang edit đè nhưng không bật `slugDirty` → slug hiển thị ≠ slug lưu |
| 7 | Trung | `resources/js/pages/writing-profiles/create/index.vue:943` | Tab file nhận `.doc/.docx` nhưng đọc bằng `readAsText` (ra rác); file rỗng vẫn báo "Đã tải lên"; thiếu `reader.onerror` |
| 8 | Trung | `resources/js/pages/writing-profiles/edit/[id].vue:1037` | Chọn "Độ dài tùy chỉnh" nhưng bỏ trống min/max → brief gửi `null` → 422 lặp lại ở mọi bước sandbox |
| 9 | Trung | `resources/js/pages/writing-profiles/edit/[id].vue:1461` | `persistSampleToDb(silent=true)` nuốt lỗi lưu — user thấy snack thành công, F5 là mất bản thử |
| 10 | Trung | `app/Http/Controllers/WritingSandboxController.php:241` | `sandbox_samples_json` đọc-sửa-ghi không khóa/transaction → race mất bản thử khi 2 tab lưu song song |
| 11 | Trung | `app/Models/WritingProfileAnalysis.php:334` | Lỗi transient giữ `running` — worker chết giữa 2 attempt → bản ghi kẹt `running` vĩnh viễn, không có reaper |
| 12 | Trung | `resources/js/stores/writingStyle.js:112` | `fetchProfiles()` cap 100 bản ghi + cache `profilesLoaded` không refetch → KPI/list sai khi >100 profile |
| 13 | Thấp | `app/Services/WritingProfileSnapshotService.php:160` | `styleSummary` chỉ giữ 8/15 khóa DNA + bỏ `voice_texture_instructions` — planner không thấy một nửa DNA đã giải mã |
| 14 | Thấp | `config/pipeline.php:93` | Khóa `ai.brief_writer_timeout` chết — service đọc `brief.suggest.timeout` |
| 15 | Thấp | `resources/js/services/writingStyleApi.js:225` + header `edit/[id].vue` | Docblock "Chi tiết triển khai" nhân bản 4+ nơi; header trang edit liệt kê 4 hàm không tồn tại |

**Quyết định cần người dùng xác nhận trước khi vào lô B và lô E (đã ghi chú tại chỗ):**
- #2: chọn phương án (a) thêm nút "Lập lại dàn ý" vào template + tự động refresh khi 422 fingerprint, hay (b) bỏ hàm + hạ `profile_version` khỏi fingerprint.
- #3: có bắt buộc bước "Duyệt dàn ý" thủ công không, hay giữ auto-approve nhưng ghi rõ vào UI?
- #7: giữ hay bỏ định dạng `.doc/.docx` ở accept (hiện backend `source_type=file` cũng chưa hỗ trợ — chỉ mới phân tích nội dung đã dán)?

---

## Lô A — Trạng thái hồ sơ khi lưu (FE edit)

**A1. Không ghi đè `status` theo toggle (#1)**
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — trong `saveProfile()`:
  - Đổi `status: profileData.isActive ? 'active' : 'draft'` thành `status: profileData.status` (đã load từ `p.status` ở `applyProfileToForm`).
  - `is_enabled` giữ theo toggle `profileData.isActive` (ý nghĩa đúng của nút gạt).
  - Nếu UI banner cần hiển thị trạng thái, thêm select "Trạng thái" (draft/active) cạnh toggle kích hoạt — hiện `profileData.status` là write-only-dead.
- [t] `Modules/WritingStyle/tests/Feature/WritingStyleApiTest.php` — thêm test: PUT profile `status=draft, is_enabled=true` với payload không chứa `status` → response giữ nguyên `status=draft`.

## Lô B — Duyệt dàn ý & fingerprint pipeline (FE edit + BE)

**B1. Sửa lỗi fingerprint không thoát được (#2)** — *cần xác nhận phương án*
- Phương án (a) — khuyến nghị:
  - [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — trong khối "Sẵn sàng cho Bước N" (template ~4260) thêm nút "Lập lại dàn ý" gọi `refreshSandboxOutline(sample)` khi `sample.steps.length > 1 && !sample.saved_to_db === false` (hiện có sẵn hàm, chỉ thiếu wire).
  - [f] Cùng file — trong `runNextSandboxStep()`, bắt lỗi API có message khớp `/Dàn ý đã cũ|khác phiên bản/` → tự gọi `refreshSandboxOutline(sample)` rồi hiện snack "Đã lập lại dàn ý theo style hiện tại — kiểm tra rồi bấm tiếp tục".
- Phương án (b):
  - [f] `Modules/WritingStyle/app/Pipelines/WritingContentPlan.php` — `fingerprint()` bỏ `'provenance' => $snapshot->provenance`, thay bằng các trường ổn định (`profile_id`, `compiler_version`) để đổi description không phá dàn ý đang dở; đồng thời xóa `refreshSandboxOutline` + docblock trỏ tới.
- [t] `Modules/WritingStyle/tests/Unit/WritingPromptPackTest.php` (hoặc test mới `SandboxFingerprintTest`) — test: chạy topic_outline → sửa `style_instructions` profile (không đổi brief) → chạy article_writer: phương án (a) chấp nhận 422, phương án (b) phải pass.

**B2. Bước duyệt dàn ý thủ công (#3)** — *cần xác nhận*
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — thêm state `outlineApprovedIds = ref(new Set())`; trong card sample tier long, sau bước 1 hiển thị nội dung `sample.artifacts.topic_outline.sections` (title + key_points) và checkbox "Tôi đã duyệt dàn ý này".
- [f] `runNextSandboxStep()` — đổi `outline_approved: Boolean(sample.artifacts?.topic_outline)` thành `outline_approved: outlineApprovedIds.value.has(sample.id)`; disable nút "Tiếp tục" khi chưa duyệt.
- [t] FE smoke: chạy tier long → không tick duyệt → nút Tiếp tục disabled; tick → gửi `outline_approved=true`.

**B3. Validate độ dài tùy chỉnh trước khi gửi (#8)**
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — `buildSandboxBrief()`:
  - Chỉ gửi `length` khi đủ bộ: `form.lengthUnit && Number(form.lengthMin) > 0 && Number(form.lengthMax) > 0`; thiếu → trả `null` (dùng preset theo tier) thay vì `{unit, min: null, max: null}`.
  - Trong `SandboxWritingBrief.vue` hoặc trang edit, thêm rule AppTextField min/max: bắt buộc + `Number` hợp lệ khi `lengthUnit` được chọn; thêm cảnh báo khi `max < min`.
- [t] BE đã có rule `required_with` — thêm test FE bằng tay hoặc unit test helper buildSandboxBrief nếu tách ra file utils.

## Lô C — Lưu bản viết thử (samples)

**C1. Hiện lỗi khi persist im lặng (#9)**
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — `persistSampleToDb()`:
  - Đổi nhánh catch: khi `silent=true` vẫn hiện snack `error` với nội dung "Không lưu được bản viết thử vào hồ sơ — dữ liệu sẽ mất khi tải lại trang" (silent chỉ áp dụng cho snack thành công).
  - Trả `boolean` thành công để caller (runSandboxDemo/runNextSandboxStep) biết và chỉnh nội dung snack chính (vd "Đã tạo bản ghi NHƯNG chưa lưu được vào DB").
- [t] FE smoke: mock API lỗi 500 khi save → snack lỗi xuất hiện, sample vẫn hiển thị với badge chưa lưu.

**C2. Chặn race đọc-sửa-ghi `sandbox_samples_json` (#10)**
- [f] `Modules/WritingStyle/app/Http/Controllers/WritingSandboxController.php` — `saveSample()` và `deleteSample()`:
  - Bọc toàn bộ vào `DB::transaction(function () ...)`; bên trong, đọc lại profile bằng `WritingProfile::whereKey($writingProfile->getKey())->lockForUpdate()->first()` và thao tác trên bản fresh (không dùng `$writingProfile->sandbox_samples_json` đã load từ route binding).
  - `update()` cũng thực hiện trong transaction trên model fresh.
- [t] `Modules/WritingStyle/tests/Feature/WritingStyleApiTest.php` — test gọi saveSample 2 lần liên tiếp trong cùng test (mô phỏng 2 request) với cùng profile → cả 2 sample đều tồn tại trong `sandbox_samples_json`.

## Lô D — Luồng giải mã (analyze)

**D1. Reaper cho analysis kẹt `running` (#11)**
- [m] `Modules/WritingStyle/app/Console/Commands/ReapStaleAnalysesCommand.php` — command `writingstyle:reap-stale-analyses`:
  - Tìm `WritingProfileAnalysis` có `status=running` và `updated_at` cũ hơn `(job.timeout × job.tries) + đệm 120s` (đọc từ `config('writingstyle.analysis.job.*')`).
  - `markFailed('worker_lost', 'Queue worker bị dừng giữa chừng — hãy phân tích lại')` + fail SystemTaskRun tương ứng (qua TaskTrackerContract, tìm theo taskable_id).
  - Đăng ký schedule `->everyFiveMinutes()` trong `WritingStyleServiceProvider::boot()` (hoặc `routes/console.php` nếu app dùng nơi đó).
- [f] `Modules/WritingStyle/app/Services/WritingStyleAnalyzerService.php` — `dispatchAnalysis()`: thêm điều kiện dedupe `->where('updated_at', '>=', now()->subMinutes($staleMinutes))` để không trả bản ghi zombie cho FE poll.
- [t] `Modules/WritingStyle/tests/Unit/AnalyzeRetryClassificationTest.php` — test: analysis `running` với `updated_at` cũ hơn ngưỡng → command chạy → status `failed` + error_code `worker_lost`; bản mới (updated_at gần) không bị động vào.

**D2. Guard ownership + re-link analysis (#4)**
- [f] `Modules/WritingStyle/app/Http/Controllers/WritingProfileController.php` — `store()`:
  - Sau khi find analysis: nếu `$analysis->created_by !== $request->user()?->id` và user không có quyền `writing.profile.manage` → 422 "Phiên phân tích không thuộc về bạn".
  - Nếu `$analysis->draft_profile_id` đã khác null và khác profile đang tạo → 422 "Phiên phân tích đã được gắn cho hồ sơ khác"; cho phép re-link cùng profile (idempotent khi user Lưu lần 2).
  - Bọc `WritingProfile::create` + gán `source_hash`/`analysis_metadata_json` + `$linkedAnalysis->save()` trong `DB::transaction`.
- [t] `Modules/WritingStyle/tests/Feature/WritingStyleApiTest.php` — 2 test: (1) user B POST với analysis_id của user A → 422; (2) POST 2 lần cùng analysis_id → lần 2 gắn vào profile mới bị chặn, lần lặp lại cùng profile vẫn OK.

## Lô E — FE nhập liệu & danh sách

**E1. Slug không bị đè khi user tự gõ (#6)**
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/create/index.vue` — thêm `slugTouched = ref(false)`; `SlugField` `@update:model-value` bật cờ; `runSlugSuggest()` chỉ gán `profileForm.slug = res.slug` khi `!slugTouched` (vẫn cập nhật `slugAvailable`/`slugTakenBy`).
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — trong `runSlugSuggest()`: chỉ gán `profileData.slug = res.slug` khi `!slugDirty.value`; nếu user đã dirty và suggestion khác → chỉ cập nhật trạng thái available/takenBy, không đổi ô.
- [t] FE smoke: gõ slug tay → blur name → slug giữ nguyên; ô "Trạng thái" available/taken-by vẫn cập nhật.

**E2. Tab file an toàn (#7)** — *cần xác nhận giữ/bỏ .doc/.docx*
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/create/index.vue` — `handleFileChange()`:
  - Bỏ `.doc,.docx` khỏi `accept` + cập nhật text hướng dẫn (khuyến nghị — backend cũng chưa có parser file) HOẶC thêm bước giải nén docx (phức tạp, cần confirm).
  - Khi `rawText` rỗng: `selectedFile.words = '0 từ'`, `selectedFile.status = 'File rỗng hoặc không đọc được nội dung'` + snack warning, KHÔNG báo thành công.
  - Thêm `reader.onerror = () => { reset selectedFile; snack.error }`.
- [t] FE smoke: chọn file rỗng → panel hiện trạng thái lỗi; chọn .docx sau khi bỏ accept → không chọn được.

**E3. List page không cap 100 và refetch được (#12)**
- [f] `Modules/WritingStyle/resources/js/stores/writingStyle.js` — `fetchProfiles()`:
  - Khi không có params và `profilesLoaded`: vẫn trả cache, nhưng thêm `refreshStaleProfiles()` so `Date.now() - lastFetchedAt > 60_000` thì refetch nền (không chặn render).
  - Lưu `this.total = res?.itemsLength` (đã có) và hiển thị cảnh báo khi `total > profiles.length` ("Hiển thị 100/234 — dùng bộ lọc để thu hẹp") trên `list/index.vue`.
  - Hướng dài hạn (ghi chú, không bắt buộc đợt này): chuyển sang phân trang server-side theo `docs/writing-profile-create-api.md §10.2`.
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/list/index.vue` — `onMounted` đổi thành `store.fetchProfiles({ force: true })` khi quay lại từ create/edit (hoặc dựa refresh-stale ở trên); KPI `statusCounts` hiện đã tính từ mảng cap — thêm chú thích/edge case khi `total > 100`.
- [t] FE smoke: seed >100 profile (tinker) → KPI Tổng hiển thị đúng `total`, list có cảnh báo.

## Lô F — Backend DNA & dọn dẹp

**F1. Planner nhận đủ DNA (#13)**
- [f] `Modules/WritingStyle/app/Services/WritingProfileSnapshotService.php` — `buildV3Snapshot()`:
  - `styleSummary.features` dùng `$features` nguyên vẹn (bỏ `array_intersect_key` với 8 khóa) — planner thấy đủ 15 khóa observed.
  - `guidance` thêm `voice_texture_instructions` vào danh sách khóa giữ lại.
  - Lưu ý: làm fingerprint dàn ý cũ mismatch (đã xử lý ở B1) và tăng độ dài prompt — kiểm tra `PromptPack::checkContext` với sample dài; nếu vượt, cân nhắc cắt theo độ dài trước khi đưa vào styleSummary.
- [t] `Modules/WritingStyle/tests/Unit/WritingProfileSnapshotServiceTest.php` — test: profile có đủ 15 khóa + Ô 2 → snapshot `styleSummary.features` chứa `emotion`, `sentence_rhythm`, `vocabulary`… và `guidance` có `voice_texture_instructions`.

**F2. Hợp nhất config timeout brief (#14)**
- [f] `Modules/WritingStyle/app/Services/WritingBriefSuggestService.php` — đổi `'timeout' => (int) config('writingstyle.pipeline.ai.brief_writer_timeout', config('writingstyle.brief.suggest.timeout', 60))` (pipeline.php là file "thông số AI của step" theo comment của chính nó).
- [f] `Modules/WritingStyle/config/brief.php` — chuyển key `suggest.timeout` thành alias/đổi comment trỏ sang `pipeline.ai.brief_writer_timeout` để không còn 2 nơi "tự nhận sở hữu".
- [t] Không cần test riêng — assert config resolve đúng trong test hiện có của suggest service.

**F3. Dọn docblock trùng lặp + hàm ma (#15)**
- [f] `Modules/WritingStyle/resources/js/services/writingStyleApi.js` — viết lại docblock `runSandboxStep`/`saveSandboxSample`/`deleteSandboxSample` với INPUT/OUTPUT thật (vd `{ step, artifact }`, `{ sample, samples }`), bỏ khối "Chi tiết triển khai" nhân bản.
- [f] `Modules/WritingStyle/resources/js/stores/writingStyle.js` — làm tương tự cho 3 action trùng docblock; sửa các docblock bị lỗi cắt chữ (vd `onNameBlur` ở edit/[id].vue:2148 có INPUT "- ) { runSlugSuggest(profileData.name" — hỏng do generate).
- [f] `Modules/WritingStyle/resources/js/pages/writing-profiles/edit/[id].vue` — header comment: xóa 4 hàm không tồn tại (`applyTonePreset`, `resetToneToBaseline`, `addOutlineSection`, `removeOutlineSection`) khỏi danh sách; nếu B1 phương án (a) thì thêm `refreshSandboxOutline` vào mô tả đúng (đã có ở dòng 61).

---

## Thứ tự thực thi đề xuất

1. **Lô A** (1 file FE, rủi ro thấp) → chạy ngay được.
2. **Lô C** (race + silent error) — backend transaction nhỏ.
3. **Lô D** (reaper + ownership) — cần command + schedule.
4. **Lô B** (duyệt dàn ý) — chờ xác nhận 3 câu hỏi ở đầu file.
5. **Lô E** (slug/file/list) — chờ xác nhận câu hỏi .doc/.docx.
6. **Lô F** (DNA + dọn dẹp) — sau cùng vì F1 ảnh hưởng fingerprint (đi cùng B1).

## Kiểm tra sau khi sửa

- `php artisan test Modules/WritingStyle` xanh.
- Smoke FE: create → phân tích → lưu → edit → sandbox tier long (tạo dàn ý → duyệt → viết) → lưu sample → F5 (sample còn) → list KPI.
- Kiểm `php artisan writingstyle:reap-stale-analyses` trên analysis test kẹt running.
