<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Slug;
use Modules\Core\Services\SlugService;
use Modules\Core\Registries\SlugRegistry;
use Modules\Core\Traits\HasSlug;
use Modules\Core\Contracts\SlugServiceContract;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test SlugService + HasSlug + SlugRegistry.
 * =====================================================================
 */
class SlugServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bảng model giả để gắn HasSlug.
        Schema::create('fake_posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('fake_posts');
        parent::tearDown();
    }

    private function service(): SlugServiceContract
    {
        return app(SlugServiceContract::class);
    }

    public function test_preview_slug_tieng_viet(): void
    {
        $slug = $this->service()->preview('Bài Viết Đầu Tiên!', 'post');
        $this->assertSame('bai-viet-dau-tien', $slug);
    }

    public function test_preview_slug_unique_khi_trung(): void
    {
        Slug::create([
            'sluggable_type' => 'post',
            'sluggable_id' => 1,
            'slug' => 'bai-viet',
            'locale' => 'vi',
            'is_primary' => true,
        ]);

        $slug = $this->service()->preview('Bài viết', 'post');
        $this->assertSame('bai-viet-1', $slug);
    }

    public function test_sync_for_model_tao_slug_va_giu_lich_su(): void
    {
        $post = FakePost::create(['title' => 'Tiêu đề một']);
        $slug1 = $post->slug()->first();
        $this->assertSame('tieu-de-mot', $slug1->slug);
        $this->assertTrue((bool) $slug1->is_primary);

        // Đổi tiêu đề -> slug mới làm primary, slug cũ thành lịch sử.
        $post->update(['title' => 'Tiêu đề hai']);
        $post->refresh();

        $primary = $post->slug()->first();
        $this->assertSame('tieu-de-hai', $primary->slug);

        $history = $post->slugs()->where('is_primary', false)->pluck('slug')->all();
        $this->assertContains('tieu-de-mot', $history);
    }

    public function test_resolve_tra_dto(): void
    {
        Slug::create([
            'sluggable_type' => 'post',
            'sluggable_id' => 7,
            'slug' => 'cu',
            'locale' => 'vi',
            'is_primary' => false,
        ]);
        Slug::create([
            'sluggable_type' => 'post',
            'sluggable_id' => 7,
            'slug' => 'moi',
            'locale' => 'vi',
            'is_primary' => true,
        ]);

        $old = $this->service()->resolve('cu', 'post');
        $this->assertTrue($old->found);
        $this->assertFalse($old->isPrimary);
        $this->assertTrue($old->needsRedirect());
        $this->assertSame('moi', $old->primarySlug);

        $new = $this->service()->resolve('moi', 'post');
        $this->assertTrue($new->isPrimary);
        $this->assertFalse($new->needsRedirect());

        $missing = $this->service()->resolve('khong-co', 'post');
        $this->assertFalse($missing->found);
    }

    public function test_slug_registry_alias(): void
    {
        $registry = app(SlugRegistry::class);
        $registry->register('post', FakePost::class);
        $this->assertTrue($registry->has('post'));
        $this->assertSame(FakePost::class, $registry->resolve('post'));
        $this->assertSame('post', $registry->aliasFor(FakePost::class));
    }

    /**
     * CHỨC NĂNG: khi đổi tiêu đề quay lại giá trị cũ, kích hoạt lại slug cũ
     *             thay vì tạo slug mới -1.
     * INPUT : update title A -> B -> A. OUTPUT: primary slug lần lượt là
     *         bai-a -> bai-b -> bai-a (không phải bai-a-1).
     */
    public function test_doi_tieu_de_quay_lai_giu_slug_cu(): void
    {
        $post = FakePost::create(['title' => 'Bài A']);
        $this->assertSame('bai-a', $post->slug()->first()->slug);

        // Đổi sang B -> bai-b là primary, bai-a thành lịch sử.
        $post->update(['title' => 'Bài B']);
        $post->refresh();
        $this->assertSame('bai-b', $post->slug()->first()->slug);
        $this->assertSame(['bai-a'], $post->slugs()->where('is_primary', false)->pluck('slug')->all());

        // Đổi lại A -> kích hoạt lại bai-a, không phải bai-a-1.
        $post->update(['title' => 'Bài A']);
        $post->refresh();
        $this->assertSame('bai-a', $post->slug()->first()->slug);
        $this->assertSame(['bai-b'], $post->slugs()->where('is_primary', false)->pluck('slug')->all());

        // Bảng slugs của post chỉ có 2 dòng: bai-a, bai-b.
        $this->assertSame(2, $post->slugs()->count());
    }

    /**
     * CHỨC NĂNG: khi slug bị model khác chiếm, vẫn cộng hậu tố -1.
     * INPUT : Post1 tạo bai-a, Post2 cố tạo bai-a. OUTPUT: Post2 slug = bai-a-1.
     */
    public function test_slug_bi_model_khac_chiem_van_cong_hau_to(): void
    {
        $post1 = FakePost::create(['title' => 'Bài A']);
        $this->assertSame('bai-a', $post1->slug()->first()->slug);

        $post2 = FakePost::create(['title' => 'Bài A']);
        $this->assertSame('bai-a-1', $post2->slug()->first()->slug);
    }
}

class FakePost extends Model
{
    use HasSlug;

    protected $table = 'fake_posts';
    protected $fillable = ['title'];

    protected function slugSourceColumn(): string
    {
        return 'title';
    }
}
