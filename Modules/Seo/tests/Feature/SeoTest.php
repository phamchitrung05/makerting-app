<?php

namespace Modules\Seo\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Seo\Actions\SyncSeoMetadataAction;
use Modules\Seo\Models\SeoMetadata;
use Modules\Seo\Services\SeoMetadataService;
use Modules\Seo\Traits\HasSeoMeta;
use Tests\TestCase;

/**
 * Feature test quan hệ HasSeoMeta + fallback của SeoMetadataService.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private SeoMetadataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('_test_seo_posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('description', 500)->nullable();
            $t->timestamps();
        });

        // SeoMetadataService nhận mảng cấu hình 'seo' (không cần bảng settings).
        $this->service = new SeoMetadataService([]);
    }

    /** Mảng cấu hình 'seo' giả cho test. */
    private function fakeSettings(array $over = []): array
    {
        return $over;
    }

    public function test_has_seo_meta_morph_relation(): void
    {
        $post = SeoTestPost::create(['title' => 'Bài viết A']);

        $meta = $post->seoMetadata()->create([
            'seoable_type' => $post->getMorphClass(),
            'seoable_id' => $post->id,
            'seo_title' => 'Tiêu đề tùy biến',
        ]);

        $this->assertInstanceOf(SeoMetadata::class, $post->fresh()->seo());
        $this->assertSame('Tiêu đề tùy biến', $post->seo()->seo_title);
    }

    public function test_resolve_falls_back_to_model_title(): void
    {
        config(['app.name' => 'Makerting']);
        $post = SeoTestPost::create(['title' => 'Bài viết hay']);

        $resolved = $this->service->resolve($post);

        $this->assertSame('Bài viết hay | Makerting', $resolved['title']);
        $this->assertSame('index,follow', $resolved['robots']);
    }

    public function test_resolve_uses_overridden_fields(): void
    {
        $post = SeoTestPost::create(['title' => 'Bài gốc', 'description' => 'Mô tả gốc']);
        app(SyncSeoMetadataAction::class)->execute($post, [
            'seo_title' => 'SEO Override',
            'robots_index' => false,
            'canonical_url' => 'https://x.com/custom',
        ]);

        $r = $this->service->resolve($post->fresh());

        $this->assertSame('SEO Override', $r['title']);
        $this->assertSame('https://x.com/custom', $r['canonical']);
        $this->assertSame('noindex,follow', $r['robots']);
        $this->assertSame('Mô tả gốc', $r['description']);
    }

    public function test_robots_respects_global_flags(): void
    {
        $service = new SeoMetadataService($this->fakeSettings(['global_index' => false]));
        $post = SeoTestPost::create(['title' => 'X']);
        app(SyncSeoMetadataAction::class)->execute($post, ['robots_index' => true, 'robots_follow' => true]);

        $this->assertSame('noindex,follow', $service->resolve($post->fresh())['robots']);
    }

    public function test_sync_only_saves_overridden_columns(): void
    {
        $post = SeoTestPost::create(['title' => 'P']);
        app(SyncSeoMetadataAction::class)->execute($post, ['focus_keyword' => 'kw']);

        $meta = $post->fresh()->seo();
        $this->assertSame('kw', $meta->focus_keyword);
        $this->assertNull($meta->seo_title); // không gửi -> null
        $this->assertTrue($meta->robots_index); // default
    }

    public function test_canonical_uses_base_url_and_slug(): void
    {
        $service = new SeoMetadataService($this->fakeSettings(['canonical_base_url' => 'https://site.vn']));
        $post = SeoTestPost::create(['title' => 'P']);
        $post->slug_text = 'bai-viet-p'; // giả lập HasSlug accessor

        $this->assertSame('https://site.vn/bai-viet-p', $service->resolve($post)['canonical']);
    }

    public function test_findmodel_rejects_model_without_hasseometa(): void
    {
        // Gọi resolve() của controller trực tiếp (request giả) — model không
        // dùng HasSeoMeta phải trả 404, không phải BadMethodCallException 500.
        $controller = new class(new SeoMetadataService([]), new SyncSeoMetadataAction()) extends \Modules\Seo\Http\Controllers\SeoController {
            public function exposeFind($type, $id)
            {
                $request = new \Illuminate\Http\Request();
                $request->merge(['seoable_type' => $type, 'seoable_id' => $id]);
                return $this->findModel($request);
            }
        };

        // User không dùng HasSeoMeta -> phải trả null (không 500, không resolve).
        $user = \Modules\Identity\Models\User::create([
            'name' => 'X', 'email' => 'seo-x@test.local',
            'password' => bcrypt('x'),
        ]);
        $this->assertNull($controller->exposeFind(
            \Modules\Identity\Models\User::class,
            $user->id,
        ));
    }

    public function test_findmodel_rejects_model_not_in_whitelist(): void
    {
        // Model không nằm trong whitelist SEO (không register vào
        // SeoModelRegistry) phải trả null — chặn IDOR enum id model lạ.
        $controller = new class(new SeoMetadataService([]), new SyncSeoMetadataAction()) extends \Modules\Seo\Http\Controllers\SeoController {
            public function exposeFind($type, $id)
            {
                $request = new \Illuminate\Http\Request();
                $request->merge(['seoable_type' => $type, 'seoable_id' => $id]);
                return $this->findModel($request);
            }
        };

        // Model lạ (morph alias 'seo_test_post' nhưng chưa whitelist) -> null.
        $post = SeoTestPost::create(['title' => 'X']);
        $this->assertNull($controller->exposeFind('seo_test_post', $post->id));
    }

    public function test_seosettings_co_fallback_khi_chua_seed(): void
    {
        // Xóa toàn bộ row settings 'seo' (giả lập DB chưa seed) — service vẫn
        // phải hoạt động với defaults() chứ không ném MissingSettings.
        \Illuminate\Support\Facades\DB::table('settings')
            ->where('group', 'seo')->delete();

        $service = new SeoMetadataService([]);
        $post = SeoTestPost::create(['title' => 'X']);

        // resolve() phải chạy được nhờ defaults() (không crash).
        $resolved = $service->resolve($post);
        $this->assertArrayHasKey('title', $resolved);
        $this->assertArrayHasKey('robots', $resolved);
    }

    // =====================================================================
    // HTTP Test: GET/PUT /api/v1/admin/seo/meta
    // =====================================================================

    private function admin(array $perms): \Modules\Identity\Models\User
    {
        foreach (['seo.view', 'seo.manage'] as $perm) {
            \Spatie\Permission\Models\Permission::findOrCreate($perm, 'admin');
        }

        $user = \Modules\Identity\Models\User::create([
            'name' => 'SEO Admin',
            'email' => 'seo-admin@test.local',
            'password' => bcrypt('secret123'),
        ]);
        $user->givePermissionTo($perms);

        return $user;
    }

    private function token(\Modules\Identity\Models\User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('seo')->plainTextToken];
    }

    public function test_get_meta_bi_403_khi_khong_co_quyen(): void
    {
        $user = $this->admin([]);
        $h = $this->token($user);

        $this->getJson('/api/v1/admin/seo/meta?seoable_type=seo_test_post&seoable_id=1', $h)
            ->assertStatus(403);
    }

    public function test_put_meta_bi_403_khi_chi_co_view(): void
    {
        $user = $this->admin(['seo.view']);
        $h = $this->token($user);

        $post = SeoTestPost::create(['title' => 'X']);

        $this->putJson('/api/v1/admin/seo/meta', [
            'seoable_type' => 'seo_test_post',
            'seoable_id' => $post->id,
            'seo_title' => 'X',
        ], $h)->assertStatus(403);
    }

    public function test_get_meta_404_khi_model_khong_hop_le(): void
    {
        $user = $this->admin(['seo.view']);
        $h = $this->token($user);

        // Model không dùng HasSeoMeta -> 404 (không phải 500).
        $this->getJson('/api/v1/admin/seo/meta?seoable_type=' . urlencode(\Modules\Identity\Models\User::class) . '&seoable_id=1', $h)
            ->assertStatus(404);
    }

    public function test_put_meta_422_khi_payload_sai(): void
    {
        $user = $this->admin(['seo.view', 'seo.manage']);
        $h = $this->token($user);

        app(\Modules\Seo\Registries\SeoModelRegistry::class)->register(\Modules\Seo\Tests\Feature\SeoTestPost::class);
        $post = SeoTestPost::create(['title' => 'X']);

        // canonical_url không phải http/https -> 422.
        $this->putJson('/api/v1/admin/seo/meta', [
            'seoable_type' => \Modules\Seo\Tests\Feature\SeoTestPost::class,
            'seoable_id' => $post->id,
            'canonical_url' => 'ftp://x.com',
        ], $h)->assertStatus(422)->assertJsonValidationErrors('canonical_url');

        // og_image_id không tồn tại -> 422.
        $this->putJson('/api/v1/admin/seo/meta', [
            'seoable_type' => \Modules\Seo\Tests\Feature\SeoTestPost::class,
            'seoable_id' => $post->id,
            'og_image_id' => 99999,
        ], $h)->assertStatus(422)->assertJsonValidationErrors('og_image_id');
    }

    public function test_put_meta_ghi_de_thanh_cong(): void
    {
        $user = $this->admin(['seo.view', 'seo.manage']);
        $h = $this->token($user);

        // Đăng ký whitelist model test — SeoController::findModel() yêu cầu
        // model nằm trong SeoModelRegistry + dùng HasSeoMeta.
        app(\Modules\Seo\Registries\SeoModelRegistry::class)->register(\Modules\Seo\Tests\Feature\SeoTestPost::class);

        $post = SeoTestPost::create(['title' => 'X']);

        $res = $this->putJson('/api/v1/admin/seo/meta', [
            'seoable_type' => \Modules\Seo\Tests\Feature\SeoTestPost::class,
            'seoable_id' => $post->id,
            'seo_title' => 'SEO Ghi đè',
            'robots_index' => false,
        ], $h);

        $res->assertOk()->assertJsonPath('data.meta.seo_title', 'SEO Ghi đè');
        $this->assertSame('SEO Ghi đè', $post->fresh()->seo()->seo_title);
        $this->assertFalse((bool) $post->fresh()->seo()->robots_index);
    }

    public function test_og_image_id_rejected_khi_khong_phai_public_image(): void
    {
        $user = $this->admin(['seo.view', 'seo.manage']);
        $h = $this->token($user);

        app(\Modules\Seo\Registries\SeoModelRegistry::class)->register(\Modules\Seo\Tests\Feature\SeoTestPost::class);
        $post = SeoTestPost::create(['title' => 'X']);

        // Tạo asset private (không phải public image) -> og_image_id bị từ chối.
        $asset = \Modules\Media\Models\MediaAsset::create([
            'title' => 'private', 'kind' => \Modules\Media\Models\MediaAsset::KIND_VIDEO,
            'visibility' => \Modules\Media\Models\MediaAsset::VISIBILITY_PRIVATE,
            'folder' => 'root', 'checksum_sha256' => hash('sha256', 'x'),
        ]);

        $this->putJson('/api/v1/admin/seo/meta', [
            'seoable_type' => \Modules\Seo\Tests\Feature\SeoTestPost::class,
            'seoable_id' => $post->id,
            'og_image_id' => $asset->id,
        ], $h)->assertStatus(422)->assertJsonValidationErrors('og_image_id');

        // Asset public video (đúng kind nhưng không image) -> cũng bị từ chối.
        $asset2 = \Modules\Media\Models\MediaAsset::create([
            'title' => 'video', 'kind' => \Modules\Media\Models\MediaAsset::KIND_VIDEO,
            'visibility' => \Modules\Media\Models\MediaAsset::VISIBILITY_PUBLIC,
            'folder' => 'root', 'checksum_sha256' => hash('sha256', 'y'),
        ]);
        $this->putJson('/api/v1/admin/seo/meta', [
            'seoable_type' => \Modules\Seo\Tests\Feature\SeoTestPost::class,
            'seoable_id' => $post->id,
            'og_image_id' => $asset2->id,
        ], $h)->assertStatus(422)->assertJsonValidationErrors('og_image_id');
    }

    public function test_schema_org_json_escape_script_tag(): void
    {
        $service = new SeoMetadataService([]);
        $post = SeoTestPost::create(['title' => 'X']);
        $post->seoMetadata()->create([
            'seoable_type' => $post->getMorphClass(),
            'seoable_id' => $post->id,
            'schema_org_json' => ['name' => 'x</script><script>alert(1)</script>'],
        ]);

        $resolved = $service->resolve($post->fresh());
        $schema = $resolved['schema_org'];

        // Chuỗi trong schema_org phải escape `</` -> `<\/` để không đóng sớm
        // <script type="application/ld+json"> (stored XSS). json_encode() lại
        // escape `\` -> `\\/` trong JSON, nhưng giá trị gốc đã đúng.
        $name = $schema['name'];
        $this->assertSame('x<\\/script><script>alert(1)<\\/script>', $name);
        $this->assertStringNotContainsString('</script>', $name);
        $this->assertStringContainsString('<\\/script>', $name);
    }
}

/** Model test nội bộ dùng HasSeoMeta. */
class SeoTestPost extends Model
{
    use HasSeoMeta;

    protected $table = '_test_seo_posts';
    protected $guarded = [];
    public $slug_text; // cho canonical fallback test

    public function getMorphClass()
    {
        return 'seo_test_post';
    }
}
