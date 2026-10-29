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
