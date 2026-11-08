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
 * Feature test HTTP API WritingStyle (Task 9).
 */
class WritingStyleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['writing.profile.view', 'writing.profile.create', 'writing.profile.manage', 'writing.evaluate'] as $p) {
            Permission::findOrCreate($p, 'admin');
        }
    }

    private function admin(array $perms): User
    {
        $u = User::create(['name' => 'A', 'email' => 'a'.uniqid().'@t.local', 'password' => Hash::make('x12345678')]);
        $u->givePermissionTo($perms);
        return $u;
    }

    private function token(User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
    }

    public function test_options_tra_tiers_va_rules(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $res = $this->getJson('/api/v1/admin/writing-style/writing-profiles/options', $this->token($u));
        $res->assertOk()
            ->assertJsonCount(4, 'data.tiers')
            ->assertJsonCount(16, 'data.rules');
    }

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
    private function fakeFetcher(?string $html, ?\Modules\Core\Exceptions\RemoteFetchException $e = null, int $status = 200): void
    {
        $this->app->instance(
            \Modules\Core\Contracts\SafeRemoteFetcherContract::class,
            new class($html, $e, $status) implements \Modules\Core\Contracts\SafeRemoteFetcherContract {
                public function __construct(private ?string $html, private ?\Modules\Core\Exceptions\RemoteFetchException $e, private int $status) {}

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

    public function test_can_thieu_quyen_bi_403(): void
    {
        $u = $this->admin([]); // không quyền
        $this->getJson('/api/v1/admin/writing-style/writing-profiles', $this->token($u))->assertForbidden();
    }
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

    private function makeAnalysis(string $status): WritingProfileAnalysis
    {
        return WritingProfileAnalysis::create([
            'name'           => 'Mẫu poll',
            'source_type'    => 'paste',
            'reference_text' => str_repeat('Câu mẫu giải mã. ', 20),
            'status'         => $status,
        ]);
    }

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

    public function test_show_analysis_khong_tra_reference_text(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $a = $this->makeAnalysis('pending');

        $res = $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u));

        $res->assertOk();
        $this->assertArrayNotHasKey('reference_text', $res->json('data'));
    }

    public function test_show_analysis_khong_ton_tai_404(): void
    {
        $u = $this->admin(['writing.profile.view']);

        $this->getJson('/api/v1/admin/writing-style/writing-profiles/analyses/does-not-exist', $this->token($u))
            ->assertNotFound();
    }

    public function test_show_analysis_thieu_quyen_403(): void
    {
        $u = $this->admin([]);
        $a = $this->makeAnalysis('pending');

        $this->getJson("/api/v1/admin/writing-style/writing-profiles/analyses/{$a->id}", $this->token($u))
            ->assertForbidden();
    }

    /* Nhánh failed: FE poll dừng ở status=failed và hiện error_message. */
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
}
