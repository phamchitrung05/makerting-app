<?php

namespace Modules\WritingStyle\Tests\Feature;

use Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Modules\WritingStyle\Jobs\AnalyzeWritingProfileJob;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử HTTP API hồ sơ văn phong
 * =====================================================================
 * Feature test HTTP API WritingStyle (Task 9).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - setUp(): Khởi tạo ứng dụng và đăng ký quyền cần dùng trong các test.
 * - admin(): Tạo admin kiểm thử và gán tập quyền được truyền vào.
 * - token(): Cấp header Bearer của Sanctum cho user kiểm thử.
 * - test_options_tra_tiers_va_rules(): Kiểm chứng API options trả đủ tier và câu hỏi giải mã.
 * - test_crud_profile(): Kiểm chứng CRUD hồ sơ hoạt động và cập nhật version.
 * - test_store_khong_analysis_id_origin_manual(): Kiểm chứng Hồ sơ không gắn analysis có origin manual.
 * - test_store_co_analysis_id_gan_ket_va_origin_reference(): Kiểm chứng Hồ sơ gắn analysis hoàn tất có origin reference và liên kết ngược.
 * - test_store_analysis_id_pending_khong_gan(): Kiểm chứng Analysis pending không được gắn vào hồ sơ.
 * - test_v3_profile_persists_analysis_and_unknown_slider_values(): Kiểm chứng Lưu dữ liệu V3 và giữ slider chưa xác định là null.
 * - test_store_analysis_id_khong_ton_tai_422(): Kiểm chứng Analysis ID không tồn tại bị trả 422.
 * - test_profile_slug_tu_sinh_tu_name_va_ghi_de_tuy_bien(): Kiểm chứng Slug tự sinh từ tên và cho phép ghi đè tùy chọn.
 * - test_xoa_profile_giai_phong_slug_tai_su_dung_duoc(): Kiểm chứng Xóa hồ sơ giải phóng slug để tái sử dụng.
 * - test_analyze_tra_202_va_dispatch_job(): Kiểm chứng Phân tích bài mẫu trả 202 và dispatch job.
 * - fakeFetcher(): Thay SafeRemoteFetcher bằng fake trả nội dung hoặc lỗi theo kịch bản.
 * - test_analyze_thieu_reference_text_422(): Kiểm chứng Phân tích thiếu bài mẫu trả 422.
 * - test_analyze_url_tra_202_va_dispatch_job(): Kiểm chứng Phân tích URL trả 202 và dispatch job.
 * - test_analyze_url_chon_ssrf_422(): Kiểm chứng URL bị chặn SSRF trả 422.
 * - test_analyze_url_tra_loi_http_422(): Kiểm chứng Nguồn URL trả lỗi HTTP được chuyển thành 422.
 * - test_can_thieu_quyen_bi_403(): Kiểm chứng Admin thiếu quyền bị từ chối 403.
 * - test_update_partial_khong_can_name(): Kiểm chứng Cập nhật một phần không yêu cầu gửi lại tên.
 * - test_resource_tra_version_va_updated_at(): Kiểm chứng Resource cung cấp version và thời gian.
 * - makeAnalysis(): Tạo analysis mẫu với trạng thái yêu cầu để kiểm thử polling.
 * - test_show_analysis_pending(): Kiểm chứng Polling analysis pending trả trạng thái, chưa có kết quả.
 * - test_show_analysis_succeeded_tra_result_va_draft_profile(): Kiểm chứng Polling analysis succeeded trả kết quả và hồ sơ đã liên kết.
 * - test_show_analysis_khong_tra_reference_text(): Kiểm chứng Polling không trả nội dung bài mẫu.
 * - test_show_analysis_khong_ton_tai_404(): Kiểm chứng Polling analysis không tồn tại trả 404.
 * - test_show_analysis_thieu_quyen_403(): Kiểm chứng Polling khi thiếu quyền bị trả 403.
 * - test_show_analysis_failed_tra_error_code_va_message(): Kiểm chứng Analysis failed trả mã và thông báo lỗi.
 * - test_show_analysis_draft_profile_null_khi_chua_co_draft(): Kiểm chứng Analysis chưa gắn hồ sơ trả draft_profile=null.
 * - test_slug_suggest_tra_slug_khi_chua_co(): Kiểm chứng Gợi ý slug còn trống trả khả dụng.
 * - test_slug_suggest_them_hau_to_khi_slug_da_co(): Kiểm chứng Slug đã có được gợi ý thêm hậu tố.
 * - test_slug_suggest_loai_tru_slug_cua_chinh_record_dang_sua(): Kiểm chứng Kiểm tra slug loại trừ hồ sơ hiện đang sửa.
 * - test_slug_suggest_cho_phep_slug_trung_giua_cac_loai_khac_nhau(): Kiểm chứng Các loại nội dung khác nhau dùng cùng slug được phép.
 * - test_options_tra_pipelines_cho_4_tier(): Kiểm chứng Options trả pipeline của đủ bốn tier.
 * - test_sandbox_step_topic_outline_tra_artifact(): Kiểm chứng Bước topic_outline trả artifact từ gateway giả.
 * - test_sandbox_step_article_writer_long_thieu_artifact_bi_chan_422(): Kiểm chứng Writer tier long thiếu dàn ý bị chặn 422.
 * - test_sandbox_step_article_writer_long_du_artifact_tra_200(): Kiểm chứng Writer tier long có dàn ý trả bài 200.
 * - test_save_va_delete_sandbox_sample_luu_db(): Kiểm chứng Lưu và xóa bản thử cập nhật JSON trong DB.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Container, fixtures và các request giả lập.
 * - OUTPUT: Assertions thành công hoặc lỗi kiểm thử.
 *
 * BỐI CẢNH SỬ DỤNG: PHPUnit chạy các ca hồi quy với fixture/mock trong môi trường kiểm thử.
 * =====================================================================
 */
class WritingStyleApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo ứng dụng và đăng ký quyền cần dùng trong các test
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Gọi parent setUp và ghi các permission vào DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi khởi tạo/DB truyền lên; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['writing.profile.view', 'writing.profile.create', 'writing.profile.manage', 'writing.evaluate'] as $p) {
            Permission::findOrCreate($p, 'admin');
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo admin kiểm thử và gán tập quyền được truyền vào
     * =====================================================================
     *
     * INPUT:
     * - array $perms
     *
     * OUTPUT:
     * - User dùng để gửi request có quyền
     *
     * SIDE EFFECT:
     * - Ghi user/permission trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi factory/permission truyền lên; transaction do RefreshDatabase quản lý.
     * =====================================================================
     */
    private function admin(array $perms): User
    {
        $u = User::create(['name' => 'A', 'email' => 'a'.uniqid().'@t.local', 'password' => Hash::make('x12345678')]);
        $u->givePermissionTo($perms);
        return $u;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cấp header Bearer của Sanctum cho user kiểm thử
     * =====================================================================
     *
     * INPUT:
     * - User $u
     *
     * OUTPUT:
     * - array Authorization dùng trong request JSON
     *
     * SIDE EFFECT:
     * - Tạo personal access token trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi tạo token truyền lên; transaction do RefreshDatabase quản lý.
     * =====================================================================
     */
    private function token(User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng API options trả đủ tier và câu hỏi giải mã
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_options_tra_tiers_va_rules(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $res = $this->getJson('/api/v1/admin/writing-style/writing-profiles/options', $this->token($u));
        $res->assertOk()
            ->assertJsonCount(4, 'data.tiers')
            ->assertJsonCount(16, 'data.rules');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng CRUD hồ sơ hoạt động và cập nhật version
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_crud_profile(): void
    {
        $u = $this->admin(['writing.profile.view', 'writing.profile.create', 'writing.profile.manage']);
        $t = $this->token($u);

        // store
        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Giọng Tay', 'dominant_tier' => 'short',
            'rules_json' => ['tone' => 'ấm áp và thân thiện'],
        ], $t);
        $res->assertCreated()->assertJsonPath('data.name', 'Giọng Tay');
        $id = $res->json('data.id');
        $this->assertSame('manual', WritingProfile::find($id)->origin);

        // index
        $this->getJson('/api/v1/admin/writing-style/writing-profiles', $t)->assertOk();

        // update
        $this->putJson("/api/v1/admin/writing-style/writing-profiles/{$id}", ['name' => 'Giọng Mới'], $t)
            ->assertOk()->assertJsonPath('data.name', 'Giọng Mới');
        $this->assertSame(2, WritingProfile::find($id)->version);

        // delete
        $this->deleteJson("/api/v1/admin/writing-style/writing-profiles/{$id}", [], $t)->assertNoContent();
        $this->assertNull(WritingProfile::find($id));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Hồ sơ không gắn analysis có origin manual
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_store_khong_analysis_id_origin_manual(): void
    {
        $u = $this->admin(['writing.profile.create']);
        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Manual Profile',
            'rules_json' => ['tone' => 'ấm áp và thân thiện'],
        ], $this->token($u));

        $res->assertCreated();
        $profile = WritingProfile::find($res->json('data.id'));
        $this->assertSame('manual', $profile->origin);
        $this->assertNull($profile->source_hash);
        $this->assertNull($profile->analysis_metadata_json);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Hồ sơ gắn analysis hoàn tất có origin reference và liên kết ngược
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_store_co_analysis_id_gan_ket_va_origin_reference(): void
    {
        $u = $this->admin(['writing.profile.create']);

        // Giả analysis đã succeeded — đúng dữ liệu mà phân tích thật để lại.
        $analysis = WritingProfileAnalysis::create([
            'name'           => 'Mẫu đã giải mã',
            'source_type'    => 'paste',
            'reference_text' => 'Câu mẫu văn phong cần lưu.',
            'status'         => 'succeeded',
            'result_json'    => [
                'detected_tier'      => 'medium',
                'rules_json'         => ['tone' => 'ấm áp'],
                'evidence_json'      => [],
                'style_instructions' => 'Mở bằng câu hỏi.',
                'metadata'           => ['word_count' => 12, 'sentence_count' => 3, 'detected_tier' => 'medium'],
            ],
        ]);

        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name'        => 'Saved Profile',
            'analysis_id' => $analysis->id,
            'rules_json'  => ['tone' => 'ấm áp'],
            'style_instructions' => 'Mở bằng câu hỏi.',
        ], $this->token($u));

        $res->assertCreated();
        $profile = WritingProfile::find($res->json('data.id'));
        $this->assertSame('reference', $profile->origin);
        $this->assertSame($analysis->source_hash, $profile->source_hash);
        $this->assertSame(12, $profile->analysis_metadata_json['word_count']);

        // Analysis được gắn vào profile (lịch sử phân tích hiển thị lại).
        $this->assertSame($profile->id, $analysis->fresh()->draft_profile_id);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Analysis pending không được gắn vào hồ sơ
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_store_analysis_id_pending_khong_gan(): void
    {
        $u = $this->admin(['writing.profile.create']);

        // Analysis chưa xong (pending) -> không được gắn; origin vẫn manual.
        $analysis = WritingProfileAnalysis::create([
            'source_type'    => 'paste',
            'reference_text' => 'Đang chạy.',
            'status'         => 'pending',
        ]);

        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name'        => 'Bad Link',
            'analysis_id' => $analysis->id,
            'rules_json'  => ['tone' => 'ấm áp và thân thiện'],
        ], $this->token($u));

        $res->assertCreated();
        $profile = WritingProfile::find($res->json('data.id'));
        $this->assertSame('manual', $profile->origin);
        $this->assertNull($profile->source_hash);
        $this->assertNull($analysis->fresh()->draft_profile_id);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Lưu dữ liệu V3 và giữ slider chưa xác định là null
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_v3_profile_persists_analysis_and_unknown_slider_values(): void
    {
        config(['writingstyle.prompt_version' => 3]);
        $token = $this->token($this->admin(['writing.profile.create', 'writing.profile.view']));
        $analysis = WritingProfileAnalysis::create([
            'source_type' => 'paste',
            'reference_text' => 'Nhà em gửi dâu rể vài mẫu.',
            'status' => 'succeeded',
            'prompt_version' => 3,
            'result_json' => [
                'rules_json' => ['pronouns' => 'Xưng nhà em'],
                'style_analysis_json' => ['schema_version' => 3, 'features' => []],
                'style_instructions' => 'Nói trực tiếp với dâu rể.',
                'voice_texture_instructions' => 'Dùng câu ngắn và từ gần gũi.',
                'voice_texture_sliders_json' => ['voice_timbre' => null],
                'tone_sliders_json' => ['humor' => 0],
            ],
        ]);

        $created = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Hồ sơ V3',
            'analysis_id' => $analysis->id,
        ], $token)->assertCreated();
        $id = $created->json('data.id');

        $this->getJson('/api/v1/admin/writing-style/writing-profiles/'.$id, $token)
            ->assertOk()
            ->assertJsonPath('data.rules_json.pronouns', 'Xưng nhà em')
            ->assertJsonPath('data.style_analysis_json.schema_version', 3)
            ->assertJsonPath('data.voice_texture_instructions', 'Dùng câu ngắn và từ gần gũi.')
            ->assertJsonPath('data.voice_texture_sliders_json.voice_timbre', null)
            ->assertJsonPath('data.tone_sliders_json.humor', 0)
            ->assertJsonPath('data.tone_instructions', '');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Analysis ID không tồn tại bị trả 422
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_store_analysis_id_khong_ton_tai_422(): void
    {
        $u = $this->admin(['writing.profile.create']);
        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name'        => 'X',
            'analysis_id' => '00000000-0000-0000-0000-000000000000',
            'rules_json'  => ['tone' => 'ấm áp và thân thiện'],
        ], $this->token($u));

        $res->assertUnprocessable();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Slug tự sinh từ tên và cho phép ghi đè tùy chọn
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_profile_slug_tu_sinh_tu_name_va_ghi_de_tuy_bien(): void
    {
        $u = $this->admin(['writing.profile.view', 'writing.profile.create']);
        $t = $this->token($u);

        // Tự sinh từ name khi không gửi slug.
        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Giọng Chuyên Gia Việt', 'rules_json' => [],
        ], $t);
        $res->assertCreated();
        $autoSlug = $res->json('data.slug');
        $this->assertNotEmpty($autoSlug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $autoSlug);

        // Slug tùy biến hợp lệ được ghi đè.
        $id = $res->json('data.id');
        $this->putJson("/api/v1/admin/writing-style/writing-profiles/{$id}", [
            'slug' => 'giong-custom-01',
        ], $t)->assertOk()->assertJsonPath('data.slug', 'giong-custom-01');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Xóa hồ sơ giải phóng slug để tái sử dụng
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_xoa_profile_giai_phong_slug_tai_su_dung_duoc(): void
    {
        $u = $this->admin(['writing.profile.view', 'writing.profile.create', 'writing.profile.manage']);
        $t = $this->token($u);

        // Tạo profile với slug tùy biến.
        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Giọng Xóa Thử', 'slug' => 'giong-xoa-thu', 'rules_json' => [],
        ], $t);
        $res->assertCreated();
        $id = $res->json('data.id');

        $this->assertDatabaseHas('slugs', ['slug' => 'giong-xoa-thu']);

        // Xóa cứng profile.
        $this->deleteJson("/api/v1/admin/writing-style/writing-profiles/{$id}", [], $t)->assertNoContent();

        // Slug phải được XÓA (không giữ lịch sử) — giải phóng tái sử dụng.
        $this->assertDatabaseMissing('slugs', ['slug' => 'giong-xoa-thu']);

        // Profile mới cùng name -> được dùng lại slug đó (không bị suffix).
        $res2 = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Giọng Xóa Thử', 'rules_json' => [],
        ], $t);
        $res2->assertCreated()->assertJsonPath('data.slug', 'giong-xoa-thu');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Phân tích bài mẫu trả 202 và dispatch job
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_analyze_tra_202_va_dispatch_job(): void
    {
        Queue::fake();
        $u = $this->admin(['writing.evaluate']);

        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'paste',
            'reference_text' => str_repeat('Bài mẫu phân tích DNA văn phong đủ dài. ', 4),
            'name' => 'Mẫu X',
        ], $this->token($u));

        $res->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['analysis_id', 'task_run_id']]);

        Queue::assertPushed(AnalyzeWritingProfileJob::class);
    }

    /*
     * Bind một SafeRemoteFetcherContract giả: trả body cố định hoặc ném lỗi —
     * giữ cho test nhánh url không gọi mạng thật.
     */
    /**
     * =====================================================================
     * CHỨC NĂNG: Thay SafeRemoteFetcher bằng fake trả nội dung hoặc lỗi theo kịch bản
     * =====================================================================
     *
     * INPUT:
     * - ?string $html, ?\Modules\Core\Exceptions\RemoteFetchException $e = null, int $status = 200
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Rebind fake vào container, không truy cập URL thật.
     *
     * EXCEPTION/TRANSACTION:
     * - Fake có thể ném exception được truyền vào; không mở transaction.
     * =====================================================================
     */
    private function fakeFetcher(?string $html, ?\Modules\Core\Exceptions\RemoteFetchException $e = null, int $status = 200): void
    {
        $this->app->instance(
            \Modules\Core\Contracts\SafeRemoteFetcherContract::class,
            /**
             * =====================================================================
             * CHỨC NĂNG FILE: Fake tải URL theo fixture để kiểm thử phân tích nguồn.
             * =====================================================================
             * Được bind vào container của ca kiểm thử; không gọi dịch vụ bên ngoài.
             * CÁC HÀM/METHOD TRONG FILE:
             * - __construct(), fetch(): thực thi hợp đồng bằng dữ liệu giả của test.
             * INPUT/OUTPUT CỦA CLASS (tổng thể):
             * - INPUT : URL/options và nội dung/lỗi HTTP cấu hình trước.
             * - OUTPUT: RemoteFetchResultDto hoặc RemoteFetchException đã cấu hình.
             * =====================================================================
             */
            new class($html, $e, $status) implements \Modules\Core\Contracts\SafeRemoteFetcherContract {
                /**
                 * =====================================================================
                 * CHỨC NĂNG: Giữ nội dung, lỗi và mã HTTP cho fake tải URL
                 * =====================================================================
                 *
                 * INPUT:
                 * - private ?string $html, private ?\Modules\Core\Exceptions\RemoteFetchException $e, private int $status
                 *
                 * OUTPUT:
                 * - Instance fake giữ html, exception và status của kịch bản.
                 *
                 * SIDE EFFECT:
                 * - Gán thuộc tính trong bộ nhớ; không gọi AI hoặc ghi DB.
                 *
                 * EXCEPTION/TRANSACTION:
                 * - Lỗi kiểu dữ liệu truyền về caller; không mở transaction.
                 * =====================================================================
                 */
                public function __construct(private ?string $html, private ?\Modules\Core\Exceptions\RemoteFetchException $e, private int $status) {}

                /**
                 * =====================================================================
                 * CHỨC NĂNG: Trả DTO tải URL giả hoặc ném lỗi được cấu hình
                 * =====================================================================
                 *
                 * INPUT:
                 * - string $url, array $options = []
                 *
                 * OUTPUT:
                 * - RemoteFetchResultDto với body/status/finalUrl/contentType giả
                 *
                 * SIDE EFFECT:
                 * - Không gọi mạng.
                 *
                 * EXCEPTION/TRANSACTION:
                 * - RemoteFetchException khi fake được cấu hình lỗi; không mở transaction.
                 * =====================================================================
                 */
                public function fetch(string $url, array $options = []): \Modules\Core\DTOs\RemoteFetchResultDto
                {
                    if ($this->e !== null) {
                        throw $this->e;
                    }

                    return new \Modules\Core\DTOs\RemoteFetchResultDto(
                        status: $this->status,
                        body: $this->html ?? '',
                        headers: [],
                        finalUrl: $url,
                        contentType: 'text/html; charset=utf-8',
                    );
                }
            },
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Phân tích thiếu bài mẫu trả 422
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_analyze_thieu_reference_text_422(): void
    {
        Queue::fake();
        $u = $this->admin(['writing.evaluate']);

        // paste không reference_text -> validate 422 từ FormRequest.
        $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'paste',
            'reference_text' => '',
        ], $this->token($u))->assertUnprocessable();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Phân tích URL trả 202 và dispatch job
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_analyze_url_tra_202_va_dispatch_job(): void
    {
        Queue::fake();
        $u = $this->admin(['writing.evaluate']);

        $html = '<html><head><title>Bài viết URL thật</title></head><body><article><p>'
            . str_repeat('Câu nội dung bài viết đủ dài để trích xuất. ', 4) . '</p></article></body></html>';
        $this->fakeFetcher($html);

        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'url',
            'source_url' => 'https://public.blog/bai-viet',
        ], $this->token($u));

        $res->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.meta.domain', 'public.blog')
            ->assertJsonPath('data.meta.title', 'Bài viết URL thật');

        Queue::assertPushed(AnalyzeWritingProfileJob::class);

        // reference_text được backend bóc từ HTML — không phải FE gửi.
        $analysis = WritingProfileAnalysis::latest('id')->first();
        $this->assertStringContainsString('Câu nội dung bài viết', $analysis->reference_text);
        $this->assertStringNotContainsString('<article>', $analysis->reference_text);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng URL bị chặn SSRF trả 422
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_analyze_url_chon_ssrf_422(): void
    {
        Queue::fake();
        $u = $this->admin(['writing.evaluate']);

        // Báo cho fake fetcher ném exception CHẶN — mô phỏng IP nội bộ bị từ
        // chối trước khi một byte nào ra ngoài (assertUrlAllowed).
        $this->fakeFetcher(null, \Modules\Core\Exceptions\RemoteFetchException::blocked('http://10.0.0.1', 'IP thuoc dai noi bo'));

        $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'url',
            'source_url' => 'http://10.0.0.1/internal',
        ], $this->token($u))->assertUnprocessable();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Nguồn URL trả lỗi HTTP được chuyển thành 422
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_analyze_url_tra_loi_http_422(): void
    {
        Queue::fake();
        $u = $this->admin(['writing.evaluate']);

        // Fetcher trả 404 (SafeRemoteFetcher không ném) -> controller bắt 422.
        $this->fakeFetcher('<html><body>404</body></html>', null, 404);

        $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'url',
            'source_url' => 'https://blog.example/missing',
        ], $this->token($u))->assertUnprocessable();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Admin thiếu quyền bị từ chối 403
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_can_thieu_quyen_bi_403(): void
    {
        $u = $this->admin([]); // không quyền
        $this->getJson('/api/v1/admin/writing-style/writing-profiles', $this->token($u))->assertForbidden();
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Cập nhật một phần không yêu cầu gửi lại tên
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_update_partial_khong_can_name(): void
    {
        $u = $this->admin(['writing.profile.create']);
        $t = $this->token($u);
        $p = WritingProfile::create([
            'name' => 'Gốc', 'dominant_tier' => 'long', 'origin' => 'manual',
            'status' => 'draft', 'is_enabled' => false,
            'rules_json' => [], 'evidence_json' => [],
        ]);

        // PUT chỉ bật is_enabled + duyệt status — KHÔNG gửi name -> trước đây 422.
        $this->putJson("/api/v1/admin/writing-style/writing-profiles/{$p->id}", [
            'is_enabled' => true,
            'status' => 'active',
        ], $t)->assertOk();

        $fresh = $p->fresh();
        $this->assertTrue((bool) $fresh->is_enabled);
        $this->assertSame('active', $fresh->status);
        $this->assertSame('Gốc', $fresh->name);
        $this->assertSame(2, $fresh->version);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Resource cung cấp version và thời gian
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_resource_tra_version_va_updated_at(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $t = $this->token($u);
        $p = WritingProfile::create([
            'name' => 'X', 'dominant_tier' => 'short', 'origin' => 'manual', 'status' => 'draft', 'rules_json' => [], 'evidence_json' => [],
        ]);

        $res = $this->getJson("/api/v1/admin/writing-style/writing-profiles/{$p->id}", $t);
        $res->assertOk()->assertJsonStructure(['data' => ['version', 'updated_at', 'created_at']]);
    }

    /* =====================================================================
     * GET /writing-profiles/analyses/{id} — endpoint FE poll sau 202.
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo analysis mẫu với trạng thái yêu cầu để kiểm thử polling
     * =====================================================================
     *
     * INPUT:
     * - string $status
     *
     * OUTPUT:
     * - WritingProfileAnalysis đã lưu
     *
     * SIDE EFFECT:
     * - INSERT analysis vào DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB truyền lên; transaction do RefreshDatabase quản lý.
     * =====================================================================
     */
    private function makeAnalysis(string $status): WritingProfileAnalysis
    {
        return WritingProfileAnalysis::create([
            'name'           => 'Mẫu poll',
            'source_type'    => 'paste',
            'reference_text' => str_repeat('Câu mẫu giải mã. ', 20),
            'status'         => $status,
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Polling analysis pending trả trạng thái, chưa có kết quả
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_pending(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $a = $this->makeAnalysis('pending');

        $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u))
            ->assertOk()
            ->assertJsonPath('data.id', $a->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.result_json', null)
            ->assertJsonPath('data.draft_profile_id', null);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Polling analysis succeeded trả kết quả và hồ sơ đã liên kết
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_succeeded_tra_result_va_draft_profile(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $a = $this->makeAnalysis('pending');

        $draft = WritingProfile::create([
            'name' => 'Draft X', 'dominant_tier' => 'long', 'origin' => 'reference',
            'status' => 'draft', 'is_enabled' => true,
            'rules_json' => ['tone' => 'trang trọng'], 'evidence_json' => [],
        ]);

        $rules = array_fill_keys(WritingProfile::RULE_KEYS, 'x');
        $rules['uncertainties'] = ['chưa có dữ liệu headings'];
        $a->markSucceeded(
            [
                'detected_tier'      => 'long',
                'rules_json'         => $rules,
                'evidence_json'      => [['rule_key' => 'tone', 'excerpt' => 'Câu mẫu', 'note' => 'n']],
                'style_instructions' => 'Viết trang trọng.',
                'metadata'           => ['word_count' => 100, 'sentence_count' => 40, 'detected_tier' => 'long'],
                'ai_run_id'          => null,
            ],
            detectedTier: 'long',
            draftProfileId: $draft->id,
        );

        $res = $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u));

        $res->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.detected_tier', 'long')
            ->assertJsonPath('data.draft_profile_id', $draft->id)
            ->assertJsonPath('data.draft_profile.id', $draft->id)
            ->assertJsonPath('data.result_json.rules_json.tone', 'x')
            ->assertJsonCount(16, 'data.result_json.rules_json')
            ->assertJsonCount(1, 'data.result_json.evidence_json')
            ->assertJsonPath('data.result_json.style_instructions', 'Viết trang trọng.')
            ->assertJsonPath('data.result_json.metadata.word_count', 100);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Polling không trả nội dung bài mẫu
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_khong_tra_reference_text(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $a = $this->makeAnalysis('pending');

        $res = $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u));

        $res->assertOk();
        $this->assertArrayNotHasKey('reference_text', $res->json('data'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Polling analysis không tồn tại trả 404
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_khong_ton_tai_404(): void
    {
        $u = $this->admin(['writing.profile.view']);

        $this->getJson('/api/v1/admin/writing-style/writing-profiles/analyses/does-not-exist', $this->token($u))
            ->assertNotFound();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Polling khi thiếu quyền bị trả 403
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_thieu_quyen_403(): void
    {
        $u = $this->admin([]);
        $a = $this->makeAnalysis('pending');

        $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u))
            ->assertForbidden();
    }

    /* Nhánh failed: FE poll dừng ở status=failed và hiện error_message. */
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Analysis failed trả mã và thông báo lỗi
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_failed_tra_error_code_va_message(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $a = $this->makeAnalysis('pending');

        $a->markFailed('ai_timeout', 'AI không phản hồi trong 300s');

        $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u))
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'ai_timeout')
            ->assertJsonPath('data.error_message', 'AI không phản hồi trong 300s')
            ->assertJsonPath('data.result_json', null)
            ->assertJsonPath('data.draft_profile_id', null);
    }

    /* Khóa draft_profile LUÔN có mặt (giá trị null khi chưa có draft) —
     * ghim hành vi whenLoaded để FE đọc res.draft_profile không vỡ. */
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Analysis chưa gắn hồ sơ trả draft_profile=null
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_show_analysis_draft_profile_null_khi_chua_co_draft(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $a = $this->makeAnalysis('pending');

        $res = $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u));

        $res->assertOk();
        $this->assertArrayHasKey('draft_profile', $res->json('data'));
        $this->assertNull($res->json('data.draft_profile'));
    }

    /* =====================================================================
     * POST /v1/admin/core/slug-suggest — gợi ý slug realtime trên UI.
     * Trả {slug, available, taken_by}; chỉ đọc, không ghi bảng slugs.
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Gợi ý slug còn trống trả khả dụng
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_slug_suggest_tra_slug_khi_chua_co(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $t = $this->token($u);

        $res = $this->postJson('/api/v1/admin/core/slug-suggest', [
            'source'         => 'Giọng Chuyên gia Kỹ thuật',
            'sluggable_type' => 'writing_profile',
        ], $t);

        $res->assertOk()
            ->assertJsonPath('data.slug', 'giong-chuyen-gia-ky-thuat')
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.taken_by', null);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Slug đã có được gợi ý thêm hậu tố
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_slug_suggest_them_hau_to_khi_slug_da_co(): void
    {
        $u = $this->admin(['writing.profile.view', 'writing.profile.create']);
        $t = $this->token($u);

        // Tạo profile chiếm slug 'giong-x' trước.
        WritingProfile::create([
            'name' => 'Giọng X', 'dominant_tier' => 'short', 'origin' => 'manual',
            'status' => 'active', 'is_enabled' => true,
            'rules_json' => [], 'evidence_json' => [],
        ]);

        $res = $this->postJson('/api/v1/admin/core/slug-suggest', [
            'source'         => 'Giọng X',
            'sluggable_type' => 'writing_profile',
        ], $t);

        $res->assertOk()
            ->assertJsonPath('data.slug', 'giong-x-1')
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.taken_by', 'giong-x');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Kiểm tra slug loại trừ hồ sơ hiện đang sửa
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_slug_suggest_loai_tru_slug_cua_chinh_record_dang_sua(): void
    {
        $u = $this->admin(['writing.profile.view', 'writing.profile.create']);
        $t = $this->token($u);

        $profile = WritingProfile::create([
            'name' => 'Giọng X', 'dominant_tier' => 'short', 'origin' => 'manual',
            'status' => 'active', 'is_enabled' => true,
            'rules_json' => [], 'evidence_json' => [],
        ]);

        // Khi sửa chính record đó, slug 'giong-x' của nó phải được coi là trống
        // (không báo "đã có" do chính nó chiếm).
        $res = $this->postJson('/api/v1/admin/core/slug-suggest', [
            'source'         => 'Giọng X',
            'sluggable_type' => 'writing_profile',
            'sluggable_id'   => $profile->id,
        ], $t);

        $res->assertOk()
            ->assertJsonPath('data.slug', 'giong-x')
            ->assertJsonPath('data.available', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Các loại nội dung khác nhau dùng cùng slug được phép
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_slug_suggest_cho_phep_slug_trung_giua_cac_loai_khac_nhau(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $t = $this->token($u);

        // Category cũng có slug 'giong-x' — nhưng writing_profile được phép trùng
        // (check theo scope sluggable_type, không phải toàn bảng).
        \Illuminate\Support\Facades\DB::table('slugs')->insert([
            'sluggable_type' => 'category',
            'sluggable_id'   => 99,
            'slug'           => 'giong-x',
            'locale'         => 'vi',
            'is_primary'     => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $res = $this->postJson('/api/v1/admin/core/slug-suggest', [
            'source'         => 'Giọng X',
            'sluggable_type' => 'writing_profile',
        ], $t);

        $res->assertOk()
            ->assertJsonPath('data.slug', 'giong-x')
            ->assertJsonPath('data.available', true);
    }

    /* =====================================================================
     * SANDBOX PIPELINE (PLAN-WRITING-STYLE-SANDBOX-PIPELINE)
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Options trả pipeline của đủ bốn tier
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_options_tra_pipelines_cho_4_tier(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $res = $this->getJson('/api/v1/admin/writing-style/writing-profiles/options', $this->token($u));

        $res->assertOk()
            ->assertJsonStructure([
                'data' => ['tiers', 'rules', 'pipelines'],
            ])
            ->assertJsonStructure([
                'data' => ['pipelines' => ['long' => ['steps']]],
            ]);

        // Long phải có 2 bước: topic_outline -> article_writer.
        $longSteps = collect($res->json('data.pipelines.long.steps') ?? [])
            ->pluck('step')->all();
        $this->assertSame(['topic_outline', 'article_writer'], $longSteps);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Bước topic_outline trả artifact từ gateway giả
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_sandbox_step_topic_outline_tra_artifact(): void
    {
        $u = $this->admin(['writing.evaluate']);
        $t = $this->token($u);

        $profile = WritingProfile::create([
            'name' => 'Giọng Long', 'dominant_tier' => 'long', 'origin' => 'manual',
            'status' => 'active', 'is_enabled' => true,
            'rules_json' => ['tone' => 'ấm áp'], 'evidence_json' => [],
        ]);

        // Mock AI trả kết quả phân tích chủ đề.
        $this->app->instance(\Modules\CoreAi\Contracts\CoreAiGatewayContract::class, new class implements \Modules\CoreAi\Contracts\CoreAiGatewayContract {
            /**
             * =====================================================================
             * CHỨC NĂNG: Trả structured response giả cho bước pipeline đang kiểm thử
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiStructuredRequest $r
             *
             * OUTPUT:
             * - AiStructuredResponse với fixture của test
             *
             * SIDE EFFECT:
             * - Không gọi provider AI; không ghi usage thật.
             *
             * EXCEPTION/TRANSACTION:
             * - Không mở transaction.
             * =====================================================================
             */
            public function executeStructured(\Modules\CoreAi\DTOs\AiStructuredRequest $r): \Modules\CoreAi\DTOs\AiStructuredResponse {
                return new \Modules\CoreAi\DTOs\AiStructuredResponse(
                    text: '{"central_message":"x"}',
                    data: [
                        'central_message' => 'Luận điểm cốt lõi test',
                        'target_angle'    => 'Góc tiếp cận độc giả mục tiêu',
                        'structure_type'  => 'storytelling',
                        'sections'        => [
                            [
                                'id' => 's1', 'parent_id' => null, 'role' => 'mở bài',
                                'heading_level' => 2, 'title' => 'Mở bài',
                                'key_points' => ['Đặt vấn đề'], 'fact_ids' => [],
                                'length_budget' => 400,
                            ],
                            [
                                'id' => 's2', 'parent_id' => null, 'role' => 'phân tích',
                                'heading_level' => 2, 'title' => 'Phân tích',
                                'key_points' => ['Dẫn chứng'], 'fact_ids' => [],
                                'length_budget' => 400,
                            ],
                        ],
                        'length'          => ['unit' => 'words', 'min' => 800, 'max' => 1200],
                        'length'          => ['unit' => 'words', 'min' => 800, 'max' => 1200],
                        'evidence_requests' => [],
                        'missing_fields'    => [],
                        'warnings'          => [],
                    ],
                    remoteModelId: 'fake',
                );
            }
            /**
             * =====================================================================
             * CHỨC NĂNG: Chặn gọi tạo ảnh ngoài phạm vi fake văn bản
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiImageRequest $r
             *
             * OUTPUT:
             * - never; ném LogicException
             *
             * SIDE EFFECT:
             * - Không gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - LogicException khi test gọi nhầm loại tác vụ; không mở transaction.
             * =====================================================================
             */
            public function generateImage(\Modules\CoreAi\DTOs\AiImageRequest $r): \Modules\CoreAi\DTOs\AiImageResponse { throw new \LogicException; }
            /**
             * =====================================================================
             * CHỨC NĂNG: Chặn gọi tổng hợp giọng nói ngoài phạm vi fake
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiSpeechRequest $r
             *
             * OUTPUT:
             * - never; ném LogicException
             *
             * SIDE EFFECT:
             * - Không gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - LogicException khi test gọi nhầm loại tác vụ; không mở transaction.
             * =====================================================================
             */
            public function synthesizeSpeech(\Modules\CoreAi\DTOs\AiSpeechRequest $r): \Modules\CoreAi\DTOs\AiSpeechResponse { throw new \LogicException; }
            /**
             * =====================================================================
             * CHỨC NĂNG: Chặn gọi nhận dạng giọng nói ngoài phạm vi fake
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiTranscribeRequest $r
             *
             * OUTPUT:
             * - never; ném LogicException
             *
             * SIDE EFFECT:
             * - Không gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - LogicException khi test gọi nhầm loại tác vụ; không mở transaction.
             * =====================================================================
             */
            public function transcribeAudio(\Modules\CoreAi\DTOs\AiTranscribeRequest $r): \Modules\CoreAi\DTOs\AiTranscribeResponse { throw new \LogicException; }
            /**
             * =====================================================================
             * CHỨC NĂNG: Trả run ID giả cho artifact pipeline trong test
             * =====================================================================
             *
             * INPUT:
             * - Không có tham số; sử dụng state hoặc config của lớp.
             *
             * OUTPUT:
             * - object có id run-1 hoặc run-2 theo fake của ca thử
             *
             * SIDE EFFECT:
             * - Không truy vấn AiRun hoặc gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - Không mở transaction.
             * =====================================================================
             */
            public function lastRun() { return (object) ['id' => 'run-1']; }
        });

        $res = $this->postJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox/steps/topic_outline",
            ['topic' => 'chủ đề X', 'target_tier' => 'long'],
            $t,
        );

        $res->assertOk()
            ->assertJsonPath('data.step', 'topic_outline')
            ->assertJsonPath('data.artifact.central_message', 'Luận điểm cốt lõi test')
            ->assertJsonCount(2, 'data.artifact.sections')
            ->assertJsonPath('data.artifact.schema_version', 3);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Writer tier long thiếu dàn ý bị chặn 422
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_sandbox_step_article_writer_long_thieu_artifact_bi_chan_422(): void
    {
        $u = $this->admin(['writing.evaluate']);
        $t = $this->token($u);

        $profile = WritingProfile::create([
            'name' => 'Giọng Long', 'dominant_tier' => 'long', 'origin' => 'manual',
            'status' => 'active', 'is_enabled' => true,
            'rules_json' => ['tone' => 'ấm áp'], 'evidence_json' => [],
        ]);

        // Gọi article_writer ở tier long mà KHÔNG truyền artifacts.topic_outline.
        $res = $this->postJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox/steps/article_writer",
            ['topic' => 'chủ đề X', 'target_tier' => 'long'],
            $t,
        );

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Writer tier long có dàn ý trả bài 200
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_sandbox_step_article_writer_long_du_artifact_tra_200(): void
    {
        $u = $this->admin(['writing.evaluate']);
        $t = $this->token($u);

        $profile = WritingProfile::create([
            'name' => 'Giọng Long', 'dominant_tier' => 'long', 'origin' => 'manual',
            'status' => 'active', 'is_enabled' => true,
            'rules_json' => ['tone' => 'ấm áp'], 'evidence_json' => [],
        ]);

        // Mock AI trả text viết bài.
        $this->app->instance(\Modules\CoreAi\Contracts\CoreAiGatewayContract::class, new class implements \Modules\CoreAi\Contracts\CoreAiGatewayContract {
            /**
             * =====================================================================
             * CHỨC NĂNG: Trả structured response giả cho bước pipeline đang kiểm thử
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiStructuredRequest $r
             *
             * OUTPUT:
             * - AiStructuredResponse với fixture của test
             *
             * SIDE EFFECT:
             * - Không gọi provider AI; không ghi usage thật.
             *
             * EXCEPTION/TRANSACTION:
             * - Không mở transaction.
             * =====================================================================
             */
            public function executeStructured(\Modules\CoreAi\DTOs\AiStructuredRequest $r): \Modules\CoreAi\DTOs\AiStructuredResponse {
                if ($r->operation === 'sandbox_step_topic_outline_v3') {
                    return new \Modules\CoreAi\DTOs\AiStructuredResponse(
                        text: '{"central_message":"x"}',
                        data: [
                            'central_message' => 'Luận điểm cốt lõi',
                            'target_angle'    => 'Góc độc giả',
                            'structure_type'  => 'storytelling',
                            'sections'        => [[
                                'id' => 's1', 'parent_id' => null, 'role' => 'mở bài',
                                'heading_level' => 2, 'title' => 'Mở bài',
                                'key_points' => ['Đặt vấn đề'], 'fact_ids' => [],
                                'length_budget' => 900,
                            ]],
                            'evidence_requests' => [],
                            'missing_fields'    => [],
                            'warnings'          => [],
                        ],
                        remoteModelId: 'fake',
                    );
                }

                return new \Modules\CoreAi\DTOs\AiStructuredResponse(
                    text: 'Bài viết hoàn chỉnh theo DNA + dàn ý.',
                    remoteModelId: 'fake',
                );
            }
            /**
             * =====================================================================
             * CHỨC NĂNG: Chặn gọi tạo ảnh ngoài phạm vi fake văn bản
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiImageRequest $r
             *
             * OUTPUT:
             * - never; ném LogicException
             *
             * SIDE EFFECT:
             * - Không gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - LogicException khi test gọi nhầm loại tác vụ; không mở transaction.
             * =====================================================================
             */
            public function generateImage(\Modules\CoreAi\DTOs\AiImageRequest $r): \Modules\CoreAi\DTOs\AiImageResponse { throw new \LogicException; }
            /**
             * =====================================================================
             * CHỨC NĂNG: Chặn gọi tổng hợp giọng nói ngoài phạm vi fake
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiSpeechRequest $r
             *
             * OUTPUT:
             * - never; ném LogicException
             *
             * SIDE EFFECT:
             * - Không gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - LogicException khi test gọi nhầm loại tác vụ; không mở transaction.
             * =====================================================================
             */
            public function synthesizeSpeech(\Modules\CoreAi\DTOs\AiSpeechRequest $r): \Modules\CoreAi\DTOs\AiSpeechResponse { throw new \LogicException; }
            /**
             * =====================================================================
             * CHỨC NĂNG: Chặn gọi nhận dạng giọng nói ngoài phạm vi fake
             * =====================================================================
             *
             * INPUT:
             * - \Modules\CoreAi\DTOs\AiTranscribeRequest $r
             *
             * OUTPUT:
             * - never; ném LogicException
             *
             * SIDE EFFECT:
             * - Không gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - LogicException khi test gọi nhầm loại tác vụ; không mở transaction.
             * =====================================================================
             */
            public function transcribeAudio(\Modules\CoreAi\DTOs\AiTranscribeRequest $r): \Modules\CoreAi\DTOs\AiTranscribeResponse { throw new \LogicException; }
            /**
             * =====================================================================
             * CHỨC NĂNG: Trả run ID giả cho artifact pipeline trong test
             * =====================================================================
             *
             * INPUT:
             * - Không có tham số; sử dụng state hoặc config của lớp.
             *
             * OUTPUT:
             * - object có id run-1 hoặc run-2 theo fake của ca thử
             *
             * SIDE EFFECT:
             * - Không truy vấn AiRun hoặc gọi provider.
             *
             * EXCEPTION/TRANSACTION:
             * - Không mở transaction.
             * =====================================================================
             */
            public function lastRun() { return (object) ['id' => 'run-2']; }
        });

        $briefPayload = [
            'content_type' => 'storytelling',
            'length' => ['unit' => 'words', 'min' => 800, 'max' => 1200],
        ];

        // Chạy bước 1 (topic_outline) trước để lấy artifact thật có
        // input_fingerprint khớp — truyền CÙNG brief để fingerprint khớp.
        $planRes = $this->postJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox/steps/topic_outline",
            ['topic' => 'chủ đề X', 'target_tier' => 'long', 'brief' => $briefPayload],
            $t,
        );
        $planRes->assertOk();
        $artifacts = ['topic_outline' => $planRes->json('data.artifact')];

        $res = $this->postJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox/steps/article_writer",
            [
                'topic' => 'chủ đề X',
                'target_tier' => 'long',
                'outline_approved' => true,
                'brief' => $briefPayload,
                'artifacts' => $artifacts,
            ],
            $t,
        );

        $res->assertOk()
            ->assertJsonPath('data.step', 'article_writer')
            ->assertJsonPath('data.artifact.text', 'Bài viết hoàn chỉnh theo DNA + dàn ý.');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng Lưu và xóa bản thử cập nhật JSON trong DB
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên response và dữ liệu liên quan
     *
     * SIDE EFFECT:
     * - Tạo fixture, gửi HTTP nội bộ và dùng fake khi cần; không gọi AI thật.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi sai kỳ vọng; RefreshDatabase quản lý dữ liệu kiểm thử.
     * =====================================================================
     */
    public function test_save_va_delete_sandbox_sample_luu_db(): void
    {
        $u = $this->admin(['writing.evaluate', 'writing.profile.view']);
        $t = $this->token($u);

        $profile = WritingProfile::create([
            'name' => 'Giọng Lưu Thử Nghiệm', 'dominant_tier' => 'medium', 'origin' => 'manual',
            'status' => 'active', 'is_enabled' => true,
            'rules_json' => ['tone' => 'ấm áp'], 'evidence_json' => [],
        ]);

        // 1. Lưu bản thử thứ nhất
        $res1 = $this->postJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox-samples",
            [
                'topic'         => 'Chủ đề cà phê sáng',
                'target_tier'   => 'medium',
                'text'          => 'Nội dung bài viết thử số 1.',
                'system_prompt' => 'System prompt số 1',
            ],
            $t,
        );

        $res1->assertOk()
            ->assertJsonPath('data.sample.topic', 'Chủ đề cà phê sáng')
            ->assertJsonCount(1, 'data.samples');

        $sampleId1 = $res1->json('data.sample.id');
        $this->assertNotEmpty($sampleId1);

        // 2. Lưu bản thử thứ hai (sẽ đứng đầu danh sách)
        $res2 = $this->postJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox-samples",
            [
                'topic'       => 'Chủ đề trà chiều',
                'target_tier' => 'short',
                'text'        => 'Nội dung bài viết thử số 2.',
            ],
            $t,
        );

        $res2->assertOk()
            ->assertJsonCount(2, 'data.samples')
            ->assertJsonPath('data.samples.0.topic', 'Chủ đề trà chiều')
            ->assertJsonPath('data.samples.1.topic', 'Chủ đề cà phê sáng');

        // 3. Kiểm tra GET profile trả về sandbox_samples_json đầy đủ
        $showRes = $this->getJson("/api/v1/admin/writing-style/writing-profiles/{$profile->id}", $t);
        $showRes->assertOk()
            ->assertJsonCount(2, 'data.sandbox_samples_json');

        // 4. Xóa bản thử thứ nhất
        $delRes = $this->deleteJson(
            "/api/v1/admin/writing-style/writing-profiles/{$profile->id}/sandbox-samples/{$sampleId1}",
            [],
            $t,
        );

        $delRes->assertOk()
            ->assertJsonCount(1, 'data.samples')
            ->assertJsonPath('data.samples.0.topic', 'Chủ đề trà chiều');
    }
}
