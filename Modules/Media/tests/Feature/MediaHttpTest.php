<?php

namespace Modules\Media\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Modules\Identity\Models\User;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Tests\Concerns\UsesIsolatedMediaDisks;
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
    use UsesIsolatedMediaDisks;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk fake cô lập cho từng test (tránh ghi thật & tránh xung đột thư mục trên Windows).
        $this->setUpIsolatedMediaDisks();

        Schema::create('_test_posts', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('_test_posts');
        $this->tearDownIsolatedMediaDisks();
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

    public function test_update_to_private_returns_502_when_move_fails(): void
    {
        $headers = $this->token($this->admin());

        $created = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Public Original',
                'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $assetId = $created['id'];
        $asset = MediaAsset::findOrFail($assetId);
        $this->assertSame('media_public', $asset->mediaFile()?->disk);

        // Giả lập lỗi copy file sang disk private.
        $this->mock(\Spatie\MediaLibrary\MediaCollections\Filesystem::class, function ($mock) {
            $mock->shouldReceive('copyFromMediaLibrary')
                ->once()
                ->andThrow(new \RuntimeException('Simulated disk move failure'));
        });

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$assetId}", [
                'title' => 'Should Rollback Title',
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            ])
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $fresh = $asset->fresh();
        $this->assertSame(MediaAsset::VISIBILITY_PUBLIC, $fresh->visibility);
        $this->assertSame('Public Original', $fresh->title);
        $this->assertSame('media_public', $fresh->mediaFile()?->disk);
    }

    public function test_update_visibility_moves_file_between_public_and_private_disks(): void
    {
        $headers = $this->token($this->admin());

        $created = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Toggle Visibility',
                'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $assetId = $created['id'];

        $toPrivate = $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$assetId}", [
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', MediaAsset::VISIBILITY_PRIVATE)
            ->assertJsonPath('data.url', null);

        $asset = MediaAsset::findOrFail($assetId);
        $this->assertSame('media_private', $asset->mediaFile()?->disk);
        $this->assertSame([], Storage::disk('media_public')->allFiles());
        $this->assertNotEmpty(Storage::disk('media_private')->allFiles());

        $this->withHeaders($headers)
            ->putJson("/api/v1/admin/media/assets/{$assetId}", [
                'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', MediaAsset::VISIBILITY_PUBLIC);

        $asset = $asset->fresh();
        $this->assertSame('media_public', $asset->mediaFile()?->disk);
        $this->assertNotEmpty(Storage::disk('media_public')->allFiles());
    }

    public function test_stream_returns_206_with_range_header_and_416_on_out_of_range(): void
    {
        $headers = $this->token($this->admin());

        $created = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Streamable Private PNG',
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $asset = MediaAsset::findOrFail($created['id']);
        $media = $asset->mediaFile();
        $rawBytes = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
        $totalSize = strlen($rawBytes);
        $this->assertGreaterThan(20, $totalSize);

        // Full response (200)
        $fullRes = $this->withHeaders($headers)
            ->get("/api/v1/admin/media/assets/{$asset->id}/stream")
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Length', (string) $totalSize)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($rawBytes, $fullRes->streamedContent());

        // Partial range bytes=5-19 -> 206 Partial Content
        $partialRes = $this->withHeaders(array_merge($headers, ['Range' => 'bytes=5-19']))
            ->get("/api/v1/admin/media/assets/{$asset->id}/stream")
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', "bytes 5-19/{$totalSize}")
            ->assertHeader('Content-Length', '15');
        $this->assertSame(substr($rawBytes, 5, 15), $partialRes->streamedContent());

        // Suffix range bytes=-8 -> 8 byte cuối cùng
        $suffixStart = $totalSize - 8;
        $suffixEnd = $totalSize - 1;
        $suffixRes = $this->withHeaders(array_merge($headers, ['Range' => 'bytes=-8']))
            ->get("/api/v1/admin/media/assets/{$asset->id}/stream")
            ->assertStatus(206)
            ->assertHeader('Content-Range', "bytes {$suffixStart}-{$suffixEnd}/{$totalSize}")
            ->assertHeader('Content-Length', '8');
        $this->assertSame(substr($rawBytes, -8), $suffixRes->streamedContent());

        // Out of range -> 416 Range Not Satisfiable
        $tooFar = $totalSize + 50;
        $this->withHeaders(array_merge($headers, ['Range' => "bytes={$tooFar}-"]))
            ->get("/api/v1/admin/media/assets/{$asset->id}/stream")
            ->assertStatus(416)
            ->assertHeader('Content-Range', "bytes */{$totalSize}");
    }

    public function test_index_and_options_do_not_trigger_n_plus_one_media_queries(): void
    {
        $headers = $this->token($this->admin());

        for ($i = 0; $i < 10; $i++) {
            $img = imagecreatetruecolor(10 + $i, 10);
            imagefill($img, 0, 0, imagecolorallocate($img, ($i * 25) % 255, 100, 150));
            $tmp = tempnam(sys_get_temp_dir(), 'n1_') . '.png';
            imagepng($img, $tmp);
            imagedestroy($img);

            $file = new UploadedFile($tmp, "img-{$i}.png", 'image/png', null, true);
            $this->withHeaders($headers)
                ->post('/api/v1/admin/media/assets', ['file' => $file, 'title' => "Img {$i}"], ['Accept' => 'application/json'])
                ->assertCreated();
        }

        // Gọi query count trên index (10 items) và options (10 items): số query phải nhỏ và cố định (không >= 10 query media riêng lẻ).
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets?itemsPerPage=24')
            ->assertOk()
            ->assertJsonCount(10, 'data.items');
        $indexMediaQueries = array_filter(
            DB::getQueryLog(),
            fn (array $q) => str_contains($q['query'], 'from "media"') || str_contains($q['query'], 'from `media`'),
        );
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(2, count($indexMediaQueries), 'index() không được bắn N+1 query tới bảng media.');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets/options')
            ->assertOk()
            ->assertJsonCount(10, 'data');
        $optionsMediaQueries = array_filter(
            DB::getQueryLog(),
            fn (array $q) => str_contains($q['query'], 'from "media"') || str_contains($q['query'], 'from `media`'),
        );
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(1, count($optionsMediaQueries), 'options() không được bắn N+1 query tới bảng media.');
    }

    public function test_attach_many_allows_duplicate_ids_and_reordering_existing_within_limit(): void
    {
        app(\Modules\Media\Registries\MediaSlotRegistry::class)->register('post.two_photos', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE],
            'maxItems' => 2,
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            'label' => 'Tối đa 2 ảnh',
        ]);

        $headers = $this->token($this->admin());
        $a1 = MediaAsset::create(['kind' => 'image', 'title' => 'A1', 'visibility' => 'public', 'folder' => 'x', 'checksum_sha256' => str_repeat('1', 64)]);
        $a2 = MediaAsset::create(['kind' => 'image', 'title' => 'A2', 'visibility' => 'public', 'folder' => 'x', 'checksum_sha256' => str_repeat('2', 64)]);
        $a3 = MediaAsset::create(['kind' => 'image', 'title' => 'A3', 'visibility' => 'public', 'folder' => 'x', 'checksum_sha256' => str_repeat('3', 64)]);
        $post = TestPostModel::create(['title' => 'Slot Limit Post']);

        // Gửi [a1, a1, a2] (3 phần tử nhưng chỉ 2 ID duy nhất <= maxItems 2) -> phải 200 OK.
        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/media/usages', [
                'linkable_type' => TestPostModel::class,
                'linkable_id' => $post->id,
                'slot' => 'post.two_photos',
                'media_asset_ids' => [$a1->id, $a1->id, $a2->id],
                'mode' => 'attach',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // replaceExisting=false với slot đang giữ [a1]: gửi [a1, a2] -> không bị tính trùng a1 thành 3.
        $svc = app(\Modules\Media\Contracts\MediaUsageServiceContract::class);
        $svc->detachAll($post, 'post.two_photos');
        $svc->attach($post, $a1, 'post.two_photos');
        $attached = $svc->attachMany($post, [$a1->id, $a2->id], 'post.two_photos', replaceExisting: false);
        $this->assertCount(2, $attached);
        $this->assertSame(2, $svc->count($post, 'post.two_photos'));

        // Vượt giới hạn thật (3 ID duy nhất cho maxItems=2) -> phải chặn 422.
        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/media/usages', [
                'linkable_type' => TestPostModel::class,
                'linkable_id' => $post->id,
                'slot' => 'post.two_photos',
                'media_asset_ids' => [$a1->id, $a2->id, $a3->id],
                'mode' => 'attach',
            ])
            ->assertStatus(422);
    }

    public function test_private_asset_share_link_and_share_stream_enforce_auth_signature_and_ttl(): void
    {
        $headers = $this->token($this->admin());

        $created = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Private Shared PNG',
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $asset = MediaAsset::findOrFail($created['id']);
        $media = $asset->mediaFile();
        $rawBytes = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        // Chưa đăng nhập hoặc thiếu quyền media.view không được phép xin share link.
        $this->flushHeaders();
        app('auth')->forgetGuards();
        $this->getJson("/api/v1/admin/media/assets/{$asset->id}/share-link")
            ->assertStatus(401);

        $noPermUser = User::create([
            'name' => 'No Perm',
            'email' => 'noperm-share@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
        ]);
        app('auth')->forgetGuards();
        $this->withHeaders($this->token($noPermUser))
            ->getJson("/api/v1/admin/media/assets/{$asset->id}/share-link")
            ->assertStatus(403);

        // User có media.view lấy được relative signed URL TTL 60 phút.
        app('auth')->forgetGuards();
        $shareRes = $this->withHeaders($headers)
            ->getJson("/api/v1/admin/media/assets/{$asset->id}/share-link")
            ->assertOk()
            ->assertJsonPath('data.ttl_minutes', 60);

        $signedUrl = (string) $shareRes->json('data.url');
        $this->assertStringStartsWith("/api/v1/admin/media/assets/{$asset->id}/share-stream?", $signedUrl);
        $this->assertNotEmpty($shareRes->json('data.expires_at'));

        // Người nhận không cần Bearer token vẫn stream được file & hỗ trợ Range 206.
        $this->flushHeaders();
        app('auth')->forgetGuards();
        $streamRes = $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Accept-Ranges', 'bytes');
        $this->assertSame($rawBytes, $streamRes->streamedContent());

        $rangeRes = $this->withHeaders(['Range' => 'bytes=2-9'])
            ->get($signedUrl)
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-9/' . strlen($rawBytes));
        $this->assertSame(substr($rawBytes, 2, 8), $rangeRes->streamedContent());

        // Chữ ký sai hoặc link quá hạn (>60 phút) bị từ chối 403.
        $this->get("/api/v1/admin/media/assets/{$asset->id}/share-stream?expires=9999999999&signature=invalid")
            ->assertStatus(403);

        $this->travel(61)->minutes();
        try {
            $this->get($signedUrl)->assertStatus(403);
        } finally {
            $this->travelBack();
        }
    }

    public function test_folders_expands_nested_parent_prefixes_and_filters_descendants(): void
    {
        $headers = $this->token($this->admin());

        MediaAsset::create(['kind' => 'image', 'title' => 'S1', 'visibility' => 'public', 'folder' => 'products/shoes', 'checksum_sha256' => str_repeat('a', 64)]);
        MediaAsset::create(['kind' => 'image', 'title' => 'S2', 'visibility' => 'public', 'folder' => 'products/shoes', 'checksum_sha256' => str_repeat('b', 64)]);
        MediaAsset::create(['kind' => 'image', 'title' => 'B1', 'visibility' => 'public', 'folder' => 'products/bags', 'checksum_sha256' => str_repeat('c', 64)]);
        MediaAsset::create(['kind' => 'image', 'title' => 'C1', 'visibility' => 'public', 'folder' => 'campaign/2026/summer', 'checksum_sha256' => str_repeat('d', 64)]);
        MediaAsset::create(['kind' => 'image', 'title' => 'P1', 'visibility' => 'public', 'folder' => 'products', 'checksum_sha256' => str_repeat('e', 64)]);
        MediaAsset::create(['kind' => 'image', 'title' => 'PA', 'visibility' => 'public', 'folder' => 'products-archive', 'checksum_sha256' => str_repeat('f', 64)]);

        $folders = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets/folders')
            ->assertOk()
            ->json('data');

        $byName = collect($folders)->pluck('count', 'name')->all();

        $this->assertSame([
            'campaign' => 1,
            'campaign/2026' => 1,
            'campaign/2026/summer' => 1,
            'products' => 4,
            'products-archive' => 1,
            'products/bags' => 1,
            'products/shoes' => 2,
        ], $byName);

        // Lọc theo prefix cha `products` phải trả đúng 4 asset (không dính `products-archive`).
        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets?folder=products')
            ->assertOk()
            ->assertJsonPath('data.itemsLength', 4);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets/options?folder=products')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets?folder=products/shoes')
            ->assertOk()
            ->assertJsonPath('data.itemsLength', 2);
    }

    public function test_trash_show_restore_and_force_delete_lifecycle_and_permissions(): void
    {
        $admin = $this->admin();
        $headers = $this->token($admin);

        // User chỉ có media.view (không có media.manage) để kiểm tra chặn 403 trên restore/forceDelete.
        $viewer = User::create([
            'name' => 'Media Viewer',
            'email' => 'viewer-trash@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
        ]);
        $viewer->givePermissionTo(Permission::findOrCreate('media.view', 'admin'));
        $viewerHeaders = $this->token($viewer);

        $created = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Private Trash Target',
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
                'folder' => 'trash-check',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $assetId = (int) $created['id'];
        $this->assertNotEmpty(Storage::disk('media_private')->allFiles());

        // Soft delete -> bình thường show/stream trả 404, có ?trashed=1 xem & stream bình thường.
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/admin/media/assets/{$assetId}")
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/media/assets/{$assetId}")
            ->assertNotFound();

        $this->withHeaders($headers)
            ->get("/api/v1/admin/media/assets/{$assetId}/stream")
            ->assertNotFound();

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/media/assets/{$assetId}?trashed=1")
            ->assertOk()
            ->assertJsonPath('data.id', $assetId);
        $this->assertNotNull(
            $this->withHeaders($headers)
                ->getJson("/api/v1/admin/media/assets/{$assetId}?trashed=1")
                ->json('data.deleted_at')
        );

        $this->withHeaders($headers)
            ->get("/api/v1/admin/media/assets/{$assetId}/stream?trashed=1")
            ->assertOk();

        // Thiếu media.manage bị chặn 403 khi restore / force-delete.
        app('auth')->forgetGuards();
        $this->withHeaders($viewerHeaders)
            ->postJson("/api/v1/admin/media/assets/{$assetId}/restore")
            ->assertForbidden();
        $this->withHeaders($viewerHeaders)
            ->deleteJson("/api/v1/admin/media/assets/{$assetId}/force")
            ->assertForbidden();

        // Restore -> quay lại hoạt động bình thường.
        app('auth')->forgetGuards();
        $this->withHeaders($headers)
            ->postJson("/api/v1/admin/media/assets/{$assetId}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $assetId)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertNotSoftDeleted('media_assets', ['id' => $assetId]);
        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/media/assets/{$assetId}")
            ->assertOk();

        // Soft delete lại -> forceDelete xóa hẳn DB + Spatie media + file vật lý trên disk.
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/admin/media/assets/{$assetId}")
            ->assertOk();

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/admin/media/assets/{$assetId}/force")
            ->assertOk()
            ->assertJsonPath('data.force_deleted', true);

        $this->assertDatabaseMissing('media_assets', ['id' => $assetId]);
        $this->assertSame(0, DB::table('media')->where('model_id', $assetId)->count());
        $this->assertSame([], Storage::disk('media_private')->allFiles());
    }

    public function test_stats_and_bulk_move_folder_work_and_validate(): void
    {
        $headers = $this->token($this->admin());

        $first = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $this->png(),
                'title' => 'Stats Img 1',
                'folder' => 'drafts',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        // Tạo ảnh thứ 2 khác nội dung để không bị dedupe.
        $img2 = imagecreatetruecolor(14, 14);
        imagefill($img2, 0, 0, imagecolorallocate($img2, 20, 200, 90));
        $tmp2 = tempnam(sys_get_temp_dir(), 'stats2_') . '.png';
        imagepng($img2, $tmp2);
        imagedestroy($img2);
        $file2 = new UploadedFile($tmp2, 'stats2.png', 'image/png', null, true);

        $second = $this->withHeaders($headers)
            ->post('/api/v1/admin/media/assets', [
                'file' => $file2,
                'title' => 'Stats Img 2',
                'folder' => 'drafts',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        // Xóa mềm 1 tệp -> kiểm tra stats tách đúng active và trashed.
        $this->withHeaders($headers)
            ->deleteJson('/api/v1/admin/media/assets/' . $second['id'])
            ->assertOk();

        $stats = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/media/assets/stats')
            ->assertOk()
            ->assertJsonPath('data.total_files', 1)
            ->assertJsonPath('data.trashed_files', 1)
            ->json('data');

        $this->assertSame((int) $first['size'], (int) $stats['total_size_bytes']);
        $this->assertSame((int) $second['size'], (int) $stats['trashed_size_bytes']);

        // Khôi phục lại asset thứ 2 rồi test chuyển thư mục hàng loạt + chuẩn hóa path.
        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/media/assets/' . $second['id'] . '/restore')
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/media/assets/bulk-folder', [
                'ids' => [$first['id'], $second['id']],
                'folder' => ' /campaign//tet-2027/ ',
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.folder', 'campaign/tet-2027');

        $this->assertDatabaseHas('media_assets', ['id' => $first['id'], 'folder' => 'campaign/tet-2027']);
        $this->assertDatabaseHas('media_assets', ['id' => $second['id'], 'folder' => 'campaign/tet-2027']);

        // Chặn '..' traversal khi đổi thư mục.
        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/media/assets/bulk-folder', [
                'ids' => [$first['id']],
                'folder' => '../secret',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['folder']);
    }
}

class TestPostModel extends Model
{
    use \Modules\Media\Traits\HasThumbnail;
    protected $table = '_test_posts';
    protected $fillable = ['title'];
    protected array $mediaSlots = ['thumbnail' => 'post.thumbnail'];
}
