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
