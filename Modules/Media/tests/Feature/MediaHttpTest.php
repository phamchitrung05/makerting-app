<?php

namespace Modules\Media\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Modules\Identity\Models\User;
use Modules\Media\Models\MediaAsset;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature test endpoint HTTP module Media.
 * =====================================================================
 * - index / show / slots / folders / options
 * - store upload ảnh (single + dedupe)
 * - update metadata / destroy
 * - usages attach / list / detach
 * - permission 403 khi không có quyền
 * =====================================================================
 */
class MediaHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake 3 disk media để test không ghi rác vào storage/app/media thật.
        Storage::fake('media_public');
        Storage::fake('media_private');
        Storage::fake('temp_processing');

        Schema::create('_test_posts', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('_test_posts');
        parent::tearDown();
    }

    protected function admin(): User
    {
        static $seq = 0;
        $seq++;
        $user = User::create([
            'name' => 'Media Admin',
            'email' => "media-admin-{$seq}@test.local",
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
        ]);
        foreach (['media.view', 'media.manage'] as $p) {
            $perm = Permission::findOrCreate($p, 'admin');
            $user->givePermissionTo($perm);
        }

        return $user;
    }

    protected function token(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('admin')->plainTextToken];
    }

    protected function png(): UploadedFile
    {
        $img = imagecreatetruecolor(10, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 0, 128, 255));
        $tmp = tempnam(sys_get_temp_dir(), 'img_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return new UploadedFile($tmp, 'test.png', 'image/png', null, true);
    }

    public function test_index_returns_datatable(): void
    {
        MediaAsset::create(['kind'=>'image','title'=>'A','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('a',64)]);

        $this->withHeaders($this->token($this->admin()))
            ->getJson('/api/v1/admin/media/assets')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [['id', 'kind', 'title']],
                    'itemsLength',
                ],
            ]);
    }

    public function test_slots_returns_registered(): void
    {
        $this->withHeaders($this->token($this->admin()))
            ->getJson('/api/v1/admin/media/slots')
            ->assertOk()
            ->assertJsonStructure(['data' => [['slot','allowedKinds','maxItems','visibility','label']]]);
    }

    public function test_store_uploads_image(): void
    {
        $res = $this->withHeaders($this->token($this->admin()))
            ->postJson('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Test Upload',
                'folder' => 'test',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.title', 'Test Upload')
            ->assertJsonPath('data.creator.name', 'Media Admin')
            ->assertJsonPath('data.usages_count', 0);

        $this->assertNotEmpty($res->json('data.conversions.thumb'));
        $this->assertDatabaseHas('media_assets', ['title' => 'Test Upload', 'kind' => 'image']);
    }

    public function test_store_uploads_many_files(): void
    {
        $img2 = imagecreatetruecolor(12, 12);
        imagefill($img2, 0, 0, imagecolorallocate($img2, 255, 64, 0));
        $tmp2 = tempnam(sys_get_temp_dir(), 'img2_') . '.png';
        imagepng($img2, $tmp2);
        imagedestroy($img2);
        $file2 = new UploadedFile($tmp2, 'second.png', 'image/png', null, true);

        $this->withHeaders($this->token($this->admin()))
            ->postJson('/api/v1/admin/media/assets', [
                'files' => [$this->png(), $file2],
                'folder' => 'gallery',
            ])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.folder', 'gallery')
            ->assertJsonPath('data.0.creator.name', 'Media Admin')
            ->assertJsonPath('data.0.usages_count', 0)
            ->assertJsonPath('data.1.folder', 'gallery');
    }

    public function test_store_dedupe_returns_existing(): void
    {
        $first = $this->withHeaders($this->token($this->admin()))
            ->postJson('/api/v1/admin/media/assets', ['file' => $this->png()])
            ->assertSuccessful();

        $id1 = $first->json('data.id');

        $second = $this->withHeaders($this->token($this->admin()))
            ->postJson('/api/v1/admin/media/assets', ['file' => $this->png()])
            ->assertSuccessful();

        $this->assertSame($id1, $second->json('data.id'));
        $this->assertEquals(1, MediaAsset::count());
    }

    public function test_usages_attach_list_detach(): void
    {
        $asset = MediaAsset::create(['kind'=>'image','title'=>'A','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('a',64)]);
        TestPostModel::create(['title' => 'P']);
        $post = TestPostModel::first();

        // attach
        $this->withHeaders($this->token($this->admin()))
            ->postJson('/api/v1/admin/media/usages', [
                'linkable_type' => TestPostModel::class,
                'linkable_id' => $post->id,
                'slot' => 'post.thumbnail',
                'media_asset_ids' => [$asset->id],
                'mode' => 'attach',
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // list
        $this->withHeaders($this->token($this->admin()))
            ->getJson("/api/v1/admin/media/usages?linkable_type=" . urlencode(TestPostModel::class) . "&linkable_id={$post->id}&slot=post.thumbnail")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // detach
        $this->withHeaders($this->token($this->admin()))
            ->deleteJson('/api/v1/admin/media/usages', [
                'linkable_type' => TestPostModel::class,
                'linkable_id' => $post->id,
                'slot' => 'post.thumbnail',
                'detach_all' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.detached', 1);
    }

    public function test_forbidden_without_permission(): void
    {
        $user = User::create([
            'name' => 'NoPerm',
            'email' => 'noperm@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
        ]);

        $this->withHeaders($this->token($user))
            ->getJson('/api/v1/admin/media/assets')
            ->assertForbidden();
    }

    public function test_unauthenticated_non_json_api_request_returns_401_not_route_login_500(): void
    {
        $this->withHeaders(['Accept' => 'image/webp,image/png,*/*;q=0.8'])
            ->get('/api/v1/admin/media/assets/1/stream')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_store_and_update_description_and_tags_persist_and_normalize(): void
    {
        $headers = $this->token($this->admin());

        $created = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Banner Hero',
                'description' => '  Mô tả ảnh ban đầu  ',
                'tags' => [' hero ', 'Hero', '  ', 'Khuyến Mãi'],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Mô tả ảnh ban đầu')
            ->assertJsonPath('data.tags', ['hero', 'Khuyến Mãi'])
            ->json('data');

        $assetId = $created['id'];

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$assetId}", [
                'description' => '  Mô tả đã cập nhật  ',
                'tags' => [' banner ', 'Sale', 'BANNER'],
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Mô tả đã cập nhật')
            ->assertJsonPath('data.tags', ['banner', 'Sale']);

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$assetId}", [
                'description' => '   ',
                'tags' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.tags', []);

        $fresh = MediaAsset::findOrFail($assetId);
        $this->assertNull($fresh->description);
        $this->assertSame([], $fresh->tags);
    }

    public function test_update_description_and_tags_validation_and_search(): void
    {
        $headers = $this->token($this->admin());

        $asset = MediaAsset::create([
            'kind' => 'image',
            'title' => 'Food Shot',
            'description' => 'Ảnh món ăn đặc sản miền Trung',
            'tags' => ['Chả Ram', 'Bình Định'],
            'visibility' => 'public',
            'folder' => 'food',
            'checksum_sha256' => str_repeat('b', 64),
        ]);

        MediaAsset::create([
            'kind' => 'image',
            'title' => 'Unrelated Shot',
            'description' => 'Nội thất văn phòng',
            'tags' => ['office'],
            'visibility' => 'public',
            'folder' => 'office',
            'checksum_sha256' => str_repeat('c', 64),
        ]);

        // Tìm theo mô tả
        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets?search=' . urlencode('đặc sản'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $asset->id);

        // Tìm theo tag tiếng Việt có dấu
        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets?search=' . urlencode('chả ram'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $asset->id);

        // Validate độ dài description & số lượng/độ dài/kiểu tag
        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$asset->id}", [
                'description' => str_repeat('a', 1001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$asset->id}", [
                'tags' => array_map(fn ($i) => "tag-{$i}", range(1, 31)),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags']);

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$asset->id}", [
                'tags' => [str_repeat('x', 51)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$asset->id}", [
                'tags' => [12345],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }
}

class TestPostModel extends Model
{
    use \Modules\Media\Traits\HasThumbnail;
    protected $table = '_test_posts';
    protected $fillable = ['title'];
    protected array $mediaSlots = ['thumbnail' => 'post.thumbnail'];
}
