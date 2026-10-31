<?php

namespace Modules\Media\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
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
            ->assertJsonStructure(['data' => ['items' => [['id','kind','title']]], 'data' => ['itemsLength']]);
    }

    /** @test GET /slots -> danh sách slot đã đăng ký */
    public function slots_returns_registered(): void
    {
        $this->withHeaders($this->token($this->admin()))
            ->getJson('/api/v1/admin/media/slots')
            ->assertOk()
            ->assertJsonStructure(['data' => [['slot','allowedKinds','maxItems','visibility','label']]]);
    }

    /** @test POST /assets upload ảnh thành công */
    public function store_uploads_image(): void
    {
        $res = $this->withHeaders($this->token($this->admin()))
            ->postJson('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Test Upload',
                'folder' => 'test',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.title', 'Test Upload');

        $this->assertDatabaseHas('media_assets', ['title' => 'Test Upload', 'kind' => 'image']);
    }

    /** @test POST /assets trùng file -> dedupe trả asset cũ */
    public function store_dedupe_returns_existing(): void
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

    /** @test 403 khi không có quyền */
    public function forbidden_without_permission(): void
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
}

class TestPostModel extends Model
{
    use \Modules\Media\Traits\HasThumbnail;
    protected $table = '_test_posts';
    protected $fillable = ['title'];
    protected array $mediaSlots = ['thumbnail' => 'post.thumbnail'];
}
