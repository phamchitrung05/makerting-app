<?php

namespace Modules\Taxonomy\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Models\User;
use Modules\Taxonomy\Models\Category;
use Modules\Taxonomy\Models\Tag;
use Modules\Taxonomy\Traits\HasTaxonomy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature tests cho CRUD, scope, permission, cây và trait.
 * =====================================================================
 * Kiểm chứng Category/Tag API, soft delete, slug, parent cycle validation,
 * options theo scope và syncTaxonomyWithNew() nhận ID + tên mới.
 * =====================================================================
 */
class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['taxonomy.view', 'taxonomy.manage'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        Schema::create('taxonomy_test_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    private function admin(array $permissions): User
    {
        static $sequence = 0;
        $sequence++;

        $user = User::create([
            'name' => 'Taxonomy Admin',
            'email' => "taxonomy-admin-{$sequence}@test.local",
            'password' => Hash::make('secret123'),
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function token(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('admin')->plainTextToken];
    }

    public function test_category_crud_creates_slug_and_returns_parent_tree(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view', 'taxonomy.manage']));

        $parent = $this->postJson('/api/v1/admin/taxonomy/categories', [
            'name' => 'Công nghệ', 'scope' => 'blog',
        ], $headers)->assertCreated()->assertJsonPath('data.slug', 'cong-nghe');

        $parentId = $parent->json('data.id');
        $this->postJson('/api/v1/admin/taxonomy/categories', [
            'name' => 'Trí tuệ nhân tạo', 'scope' => 'blog', 'parent_id' => $parentId,
        ], $headers)->assertCreated();

        $this->getJson('/api/v1/admin/taxonomy/categories/tree?scope=blog', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Công nghệ')
            ->assertJsonPath('data.0.children.0.name', 'Trí tuệ nhân tạo');

        $this->putJson('/api/v1/admin/taxonomy/categories/'.$parentId, [
            'name' => 'Công nghệ số', 'scope' => 'blog',
        ], $headers)->assertOk()->assertJsonPath('data.slug', 'cong-nghe-so');

        $this->deleteJson('/api/v1/admin/taxonomy/categories/'.$parentId, [], $headers)
            ->assertNoContent();
        $this->assertSoftDeleted('categories', ['id' => $parentId]);
    }

    public function test_category_update_rejects_parent_cycle(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view', 'taxonomy.manage']));

        $a = Category::create(['name' => 'A', 'scope' => 'blog']);
        $b = Category::create(['name' => 'B', 'scope' => 'blog', 'parent_id' => $a->id]);

        $this->putJson('/api/v1/admin/taxonomy/categories/'.$a->id, [
            'parent_id' => $b->id,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_options_include_shared_and_requested_scope_only(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view']));
        Category::create(['name' => 'Common category']);
        Category::create(['name' => 'Blog category', 'scope' => 'blog']);
        Category::create(['name' => 'YouTube category', 'scope' => 'youtube']);
        Tag::create(['name' => 'Common tag']);
        Tag::create(['name' => 'Blog tag', 'scope' => 'blog']);
        Tag::create(['name' => 'YouTube tag', 'scope' => 'youtube']);

        $this->getJson('/api/v1/admin/taxonomy/categories/options?scope=blog', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Blog category')
            ->assertJsonPath('data.1.name', 'Common category');
        $this->getJson('/api/v1/admin/taxonomy/tags/options?scope=blog', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Blog tag')
            ->assertJsonPath('data.1.name', 'Common tag');
    }

    public function test_scope_shared_chi_tra_danh_muc_dung_chung(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view']));
        Category::create(['name' => 'Shared1']);
        Category::create(['name' => 'Blog1', 'scope' => 'blog']);
        Category::create(['name' => 'YT1', 'scope' => 'youtube']);

        // scope=shared -> chỉ scope NULL.
        $res = $this->getJson('/api/v1/admin/taxonomy/categories?scope=shared', $headers);
        $res->assertOk();
        $names = collect($res->json('data.items'))->pluck('name')->all();
        $this->assertSame(['Shared1'], $names);

        // Không truyền scope -> tất cả.
        $all = $this->getJson('/api/v1/admin/taxonomy/categories', $headers);
        $all->assertOk();
        $this->assertSame(3, $all->json('data.itemsLength'));
    }

    public function test_scope_sentinel_bi_tu_choi_khi_luu(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view', 'taxonomy.manage']));

        // 'shared'/'any'/'all' bị cấm làm giá trị scope.
        foreach (['shared', 'any', 'all'] as $bad) {
            $this->postJson('/api/v1/admin/taxonomy/categories', [
                'name' => 'X', 'scope' => $bad,
            ], $headers)->assertUnprocessable()->assertJsonValidationErrors('scope');

            $this->postJson('/api/v1/admin/taxonomy/tags', [
                'name' => 'X', 'scope' => $bad,
            ], $headers)->assertUnprocessable()->assertJsonValidationErrors('scope');
        }
    }

    public function test_xoa_mem_cha_reparent_con_ve_goc(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view', 'taxonomy.manage']));

        $root = Category::create(['name' => 'Root', 'scope' => 'blog']);
        $parent = Category::create(['name' => 'Parent', 'scope' => 'blog', 'parent_id' => $root->id]);
        $child = Category::create(['name' => 'Child', 'scope' => 'blog', 'parent_id' => $parent->id]);

        $this->deleteJson("/api/v1/admin/taxonomy/categories/{$parent->id}", [], $headers)
            ->assertNoContent();

        // Con được reparent lên ông (root), không bị mồ côi trên cây.
        $this->assertSame($root->id, $child->fresh()->parent_id);

        // /tree vẫn trả nhánh root->child (không mất node).
        $tree = $this->getJson('/api/v1/admin/taxonomy/categories/tree?scope=blog', $headers);
        $tree->assertOk();
        $names = collect($tree->json('data'))->pluck('name')->all();
        $this->assertContains('Root', $names);
    }

    public function test_doi_scope_con_chan_lech_cha(): void
    {
        $headers = $this->token($this->admin(['taxonomy.view', 'taxonomy.manage']));

        $parent = Category::create(['name' => 'P', 'scope' => 'blog']);
        $child = Category::create(['name' => 'C', 'scope' => 'blog', 'parent_id' => $parent->id]);

        // Đổi scope con sang 'youtube' mà cha vẫn 'blog' -> phải 422.
        $this->putJson("/api/v1/admin/taxonomy/categories/{$child->id}", [
            'scope' => 'youtube',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('scope');

        // Đổi scope cha sang 'youtube' khi con ở 'blog' -> phải 422.
        $this->putJson("/api/v1/admin/taxonomy/categories/{$parent->id}", [
            'scope' => 'youtube',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('scope');

        // Đổi scope cha sang null (dùng chung) khi con 'blog' -> được (con thừa kế).
        $this->putJson("/api/v1/admin/taxonomy/categories/{$parent->id}", [
            'scope' => null,
        ], $headers)->assertOk();
    }

    public function test_tag_crud_and_missing_permission_are_handled(): void
    {
        $manager = $this->token($this->admin(['taxonomy.view', 'taxonomy.manage']));

        $this->getJson('/api/v1/admin/taxonomy/tags', $manager)->assertOk();
        $tag = $this->postJson('/api/v1/admin/taxonomy/tags', [
            'name' => 'Generative AI', 'scope' => 'blog',
        ], $manager)->assertCreated()->assertJsonPath('data.slug', 'generative-ai');

        $this->putJson('/api/v1/admin/taxonomy/tags/'.$tag->json('data.id'), [
            'name' => 'GenAI',
        ], $manager)->assertOk()->assertJsonPath('data.slug', 'genai');
    }

    public function test_user_without_manage_permission_cannot_create_tags(): void
    {
        $viewer = $this->token($this->admin(['taxonomy.view']));

        $this->postJson('/api/v1/admin/taxonomy/tags', [
            'name' => 'Generative AI', 'scope' => 'blog',
        ], $viewer)->assertForbidden();
    }

    public function test_sync_taxonomy_with_new_creates_and_syncs_categories_and_tags(): void
    {
        $entry = TaxonomyTestEntry::create(['title' => 'Test entry']);
        $existingCategory = Category::create(['name' => 'Existing', 'scope' => 'blog']);
        $existingTag = Tag::create(['name' => 'existing', 'scope' => 'blog']);

        $synced = $entry->syncTaxonomyWithNew([
            'scope' => 'blog',
            'categories' => [$existingCategory->id],
            'new_categories' => ['AI'],
            'tags' => [$existingTag->id],
            'new_tags' => ['suggested-by-ai'],
        ]);

        $this->assertCount(2, $synced['categories']);
        $this->assertCount(2, $synced['tags']);
        $this->assertSame(['AI', 'Existing'], $entry->categories()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['existing', 'suggested-by-ai'], $entry->tags()->orderBy('name')->pluck('name')->all());

        $entry->syncTaxonomyWithNew(['categories' => [], 'tags' => []]);
        $this->assertCount(0, $entry->categories);
        $this->assertCount(0, $entry->tags);
    }
}

/** Minimal domain-model fixture proving HasTaxonomy works outside its module. */
class TaxonomyTestEntry extends Model
{
    use HasTaxonomy;

    protected $table = 'taxonomy_test_entries';
    protected $fillable = ['title'];
}
