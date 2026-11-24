<?php

namespace Modules\Media\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Modules\Media\Actions\CalculateMediaChecksumAction;
use Modules\Media\Actions\ScanMediaSecurityAction;
use Modules\Media\Actions\UploadMediaAssetAction;
use Modules\Media\Actions\StoreGeneratedMediaAction;
use Modules\Media\Contracts\MediaUsageServiceContract;
use Modules\Media\DTOs\StoreGeneratedMediaDto;
use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Tests\Concerns\UsesIsolatedMediaDisks;
use Modules\Media\Traits\HasThumbnail;
use Modules\Media\Traits\HasGallery;
use Modules\Media\Traits\HasVideoAsset;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Feature test Actions + Traits + Service của Media.
 */
class MediaFeatureTest extends TestCase
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

    protected function pngFile(): UploadedFile
    {
        $img = imagecreatetruecolor(20, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        $tmp = tempnam(sys_get_temp_dir(), 'img_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return new UploadedFile($tmp, 'x.png', 'image/png', null, true);
    }

    protected function pngPath(): string
    {
        $img = imagecreatetruecolor(15, 15);
        imagefill($img, 0, 0, imagecolorallocate($img, 0, 255, 0));
        $tmp = tempnam(sys_get_temp_dir(), 'img_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return $tmp;
    }
    public function test_checksum_dedupes_same_content(): void
    {
        $checksum = new CalculateMediaChecksumAction();
        $p1 = $this->pngPath();
        $p2 = $this->pngPath();
        $h1 = $checksum->fromPath($p1);
        $h2 = $checksum->fromPath($p2);

        $this->assertSame($h1, $h2);
        @unlink($p1); @unlink($p2);
    }

    public function test_scan_blocks_php_disguised_as_png(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'evil_') . '.png';
        file_put_contents($tmp, '<?php echo "x"; ?>' . str_repeat('x', 200));

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_upload_creates_asset_with_dimensions(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $asset = $action->execute($this->pngFile(), ['folder' => 'test', 'title' => 'T']);

        $this->assertSame('image', $asset->kind);
        $this->assertSame(20, $asset->width);
        $this->assertSame(10, $asset->height);
        $this->assertNotNull($asset->mediaFile());
    }

    public function test_store_generated_saves_ai_meta(): void
    {
        $action = new StoreGeneratedMediaAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $path = $this->pngPath();
        $dto = new StoreGeneratedMediaDto(
            tmpPath: $path,
            kind: MediaAsset::KIND_IMAGE,
            title: 'AI Gen',
            folder: 'ai',
            source: 'visual_style',
            meta: ['model' => 'dall-e-3'],
        );
        $asset = $action->execute($dto);

        $this->assertSame('ai', $asset->folder);
        $this->assertNotNull($asset->mediaFile());
        @unlink($path);
    }

    public function test_traits_attach_and_sort(): void
    {
        $post = MediaTestPost::create(['title' => 'P']);
        $a1 = MediaAsset::create(['kind'=>'image','title'=>'i1','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('a',64)]);
        $a2 = MediaAsset::create(['kind'=>'image','title'=>'i2','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('b',64)]);

        $post->setThumbnail($a1);
        $this->assertTrue($post->hasThumbnail());
        $post->setThumbnail($a2);
        $this->assertSame($a2->id, $post->thumbnail()->id);

        $post->addToGallery($a1);
        $post->addToGallery($a2);
        $this->assertSame(2, $post->galleryCount());
        $post->moveGalleryItem($a2, 0);
        $this->assertTrue($post->gallery()->contains('id', $a2->id));
    }
    public function test_svg_with_script_is_blocked(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'svg_') . '.svg';
        file_put_contents($tmp, '<?xml version="1.0"?><svg><script>alert(1)</script></svg>');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_svg_with_onload_is_blocked(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'svg_') . '.svg';
        file_put_contents($tmp, '<?xml version="1.0"?><svg onload="alert(1)"></svg>');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_polyglot_payload_after_4kb_is_blocked(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'poly_') . '.png';
        file_put_contents($tmp, hex2bin('89504e470d0a1a0a') . str_repeat('A', 5000) . '<?php echo 1; ?>');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_private_upload_goes_to_media_private_disk(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $asset = $action->execute(
            $this->pngFile(),
            ['folder' => 't', 'visibility' => MediaAsset::VISIBILITY_PRIVATE],
        );

        $this->assertSame('private', $asset->visibility);
        $this->assertSame('media_private', $asset->mediaFile()?->disk);
    }

    public function test_private_asset_dto_hides_url(): void
    {
        $asset = MediaAsset::create([
            'kind' => 'image',
            'title' => 'P',
            'visibility' => 'private',
            'folder' => 'x',
            'checksum_sha256' => str_repeat('z', 64),
        ]);

        $dto = MediaItemDto::fromModel($asset);

        $this->assertNull($dto->url);
        $this->assertSame([], $dto->conversions);
    }

    public function test_octet_stream_is_blocked(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'bin_') . '.bin';
        file_put_contents($tmp, random_bytes(200));

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_png_renamed_to_zip_is_blocked(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'img_') . '.zip';
        $img = imagecreatetruecolor(10, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        imagepng($img, $tmp);
        imagedestroy($img);

        $file = new UploadedFile($tmp, 'fake.zip', 'application/zip', null, true);

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanUploadedFile($file);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_png_renamed_to_php_is_blocked(): void
    {
        $scan = new ScanMediaSecurityAction();
        $tmp = tempnam(sys_get_temp_dir(), 'img_') . '.php';
        $img = imagecreatetruecolor(10, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        imagepng($img, $tmp);
        imagedestroy($img);

        $file = new UploadedFile($tmp, 'shell.php', 'application/x-php', null, true);

        $this->expectException(\InvalidArgumentException::class);
        try {
            $scan->scanUploadedFile($file);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_upload_filename_uses_mime_extension(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $img = imagecreatetruecolor(10, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 255));
        $tmp = tempnam(sys_get_temp_dir(), 'img_') . '.php';
        imagepng($img, $tmp);
        imagedestroy($img);

        $file = new UploadedFile($tmp, 'shell.php', 'image/png', null, true);

        $ref = new \ReflectionMethod($action, 'buildFileName');
        $ref->setAccessible(true);
        $name = $ref->invoke($action, $file, 'abc123');

        $this->assertStringEndsWith('.png', $name);
        $this->assertStringNotContainsString('.php', $name);
        @unlink($tmp);
    }

    public function test_attach_to_model_without_media_slots_trait_is_blocked(): void
    {
        $post = PlainPostModel::create(['title' => 'X']);
        $asset = MediaAsset::create([
            'kind' => 'image', 'title' => 'i', 'visibility' => 'public',
            'folder' => 'x', 'checksum_sha256' => str_repeat('w', 64),
        ]);

        $svc = app(MediaUsageServiceContract::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('không dùng trait HasMediaSlots');

        $svc->attach($post, $asset, 'thumbnail');
    }
    public function test_dedupe_respects_allowed_kinds(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());

        // MP4 signature (ftyp) để finfo nhận video/mp4 thay vì text/document
        $vid = tempnam(sys_get_temp_dir(), 'v_') . '.mp4';
        file_put_contents($vid, "\x00\x00\x00\x18ftypmp42" . str_repeat("\x00", 100));
        $v1 = $action->execute(
            new UploadedFile($vid, 'v.mp4', 'video/mp4', null, true),
            ['allowed_kinds' => [MediaAsset::KIND_VIDEO]],
        );

        $vid2 = tempnam(sys_get_temp_dir(), 'v_') . '.mp4';
        file_put_contents($vid2, "\x00\x00\x00\x18ftypmp42" . str_repeat("\x00", 100));

        $this->expectException(\InvalidArgumentException::class);
        try {
            $action->execute(
                new UploadedFile($vid2, 'v.mp4', 'video/mp4', null, true),
                ['allowed_kinds' => [MediaAsset::KIND_IMAGE]],
            );
        } finally {
            @unlink($vid); @unlink($vid2);
        }
    }

    public function test_attach_many_dedupes_duplicate_ids(): void
    {
        $post = MediaTestPost::create(['title' => 'X']);
        $a = MediaAsset::create(['kind'=>'image','title'=>'a','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('m',64)]);
        $b = MediaAsset::create(['kind'=>'image','title'=>'b','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('n',64)]);

        $svc = app(MediaUsageServiceContract::class);

        $svc->attachMany($post, [$a->id, $a->id, $b->id], 'post.gallery', true);
        $this->assertSame(2, $svc->count($post, 'post.gallery'));

        $svc->attachMany($post, [$a->id], 'post.gallery', false);
        $this->assertSame(2, $svc->count($post, 'post.gallery'));
    }

    public function test_move_gallery_item_shifts_others(): void
    {
        $post = MediaTestPost::create(['title' => 'Y']);
        $a = MediaAsset::create(['kind'=>'image','title'=>'a','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('o',64)]);
        $b = MediaAsset::create(['kind'=>'image','title'=>'b','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('p',64)]);
        $c = MediaAsset::create(['kind'=>'image','title'=>'c','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('q',64)]);

        $post->addToGallery($a);
        $post->addToGallery($b);
        $post->addToGallery($c);

        // moveGalleryItem shift toàn bộ: khi c lên đầu, a+b dời xuống sau.
        $post->moveGalleryItem($c, 0);
        $ids = $post->gallery()->pluck('id')->all();
        // Kiểm thứ tự tương đối: c phải đứng trước a và b
        $posC = array_search($c->id, $ids, true);
        $posA = array_search($a->id, $ids, true);
        $posB = array_search($b->id, $ids, true);
        $this->assertNotFalse($posC);
        $this->assertLessThan($posA, $posC);
        $this->assertLessThan($posB, $posC);

        // Đưa a xuống cuối: vị trí a > vị trí b
        $post->moveGalleryItem($a, 2);
        $ids = $post->gallery()->pluck('id')->all();
        $posA = array_search($a->id, $ids, true);
        $posB = array_search($b->id, $ids, true);
        $this->assertGreaterThan($posB, $posA);
    }

    public function test_destroy_detaches_usages(): void
    {
        $post = MediaTestPost::create(['title' => 'Z']);
        $asset = MediaAsset::create(['kind'=>'image','title'=>'i','visibility'=>'public','folder'=>'x','checksum_sha256'=>str_repeat('r',64)]);

        $post->setThumbnail($asset);
        $this->assertTrue($post->hasThumbnail());

        $asset->usages()->delete();
        $asset->delete();
        $post->refresh();

        $this->assertFalse($post->hasThumbnail());
        $this->assertNull($post->thumbnail());
    }

    public function test_allowed_upload_extensions_reads_from_media_config(): void
    {
        config(['media.allowed_upload_extensions' => [
            MediaAsset::KIND_IMAGE => ['jpg'],
        ]]);

        $scan = new ScanMediaSecurityAction();
        $file = $this->pngFile();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('không được phép');

        try {
            $scan->scanUploadedFile($file);
        } finally {
            @unlink($file->getRealPath());
        }
    }

    public function test_binary_image_containing_shebang_bytes_inside_body_is_allowed(): void
    {
        $img = imagecreatetruecolor(20, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 12, 34, 56));
        $tmp = tempnam(sys_get_temp_dir(), 'shebang_ok_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        // Chèn cặp byte '#!' (0x23 0x21), '<?=' + byte nhị phân, '<?xml version=' (XMP),
        // và '<svg'/'<SvG' sau PNG IEND để mô phỏng ảnh thiết kế/nén thật tình cờ có các chuỗi này.
        file_put_contents($tmp, "\x00\x11#!#!\x00\x22<?=\x99\xaa<?xml version=\"1.0\"?><svg><SvG>", FILE_APPEND);

        $file = new UploadedFile($tmp, 'photo-real.png', 'image/png', null, true);
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());

        try {
            $asset = $action->execute($file, ['folder' => 'test']);
            $this->assertSame('image', $asset->kind);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_file_starting_with_shebang_is_blocked_with_original_filename(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sh_') . '.txt';
        // Khoảng trắng đầu khiến finfo nhận diện text/plain (kind=document) để đi tới bước quét signature shebang.
        file_put_contents($tmp, "  #!/bin/bash\nrm -rf /\n");

        $file = new UploadedFile($tmp, 'malicious-script.txt', 'text/plain', null, true);
        $scan = new ScanMediaSecurityAction();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('[malicious-script.txt]');

        try {
            $scan->scanUploadedFile($file);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_polyglot_short_echo_php_is_blocked(): void
    {
        $img = imagecreatetruecolor(10, 10);
        imagefill($img, 0, 0, imagecolorallocate($img, 10, 20, 30));
        $tmp = tempnam(sys_get_temp_dir(), 'poly_echo_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        file_put_contents($tmp, str_repeat('A', 5000) . '<?= $_GET["cmd"] ?>', FILE_APPEND);

        $file = new UploadedFile($tmp, 'poly-echo.png', 'image/png', null, true);
        $scan = new ScanMediaSecurityAction();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File [poly-echo.png] chứa signature nguy hiểm (<?=)');

        try {
            $scan->scanUploadedFile($file);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_upload_same_file_with_private_visibility_creates_new_asset(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());

        // Cùng 1 nội dung PNG nhưng tạo 2 UploadedFile riêng vì UploadedFile đầu sẽ bị Spatie di chuyển.
        $filePublic = $this->pngFile();
        $tmpPrivate = tempnam(sys_get_temp_dir(), 'priv_') . '.png';
        copy($filePublic->getRealPath(), $tmpPrivate);
        $filePrivate = new UploadedFile($tmpPrivate, 'x-private.png', 'image/png', null, true);

        $tmpPrivate2 = tempnam(sys_get_temp_dir(), 'priv2_') . '.png';
        copy($tmpPrivate, $tmpPrivate2);
        $filePrivateAgain = new UploadedFile($tmpPrivate2, 'x-private-2.png', 'image/png', null, true);

        try {
            $publicAsset = $action->execute($filePublic, [
                'folder' => 'pub',
                'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            ]);
            $privateAsset = $action->execute($filePrivate, [
                'folder' => 'priv',
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            ]);
            $privateDeduped = $action->execute($filePrivateAgain, [
                'folder' => 'priv',
                'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            ]);

            $this->assertNotSame($publicAsset->id, $privateAsset->id);
            $this->assertSame($publicAsset->checksum_sha256, $privateAsset->checksum_sha256);
            $this->assertSame(MediaAsset::VISIBILITY_PUBLIC, $publicAsset->visibility);
            $this->assertSame('media_public', $publicAsset->mediaFile()?->disk);
            $this->assertSame(MediaAsset::VISIBILITY_PRIVATE, $privateAsset->visibility);
            $this->assertSame('media_private', $privateAsset->mediaFile()?->disk);

            // Upload lần 2 với cùng visibility private + cùng checksum -> dedupe đúng về asset private.
            $this->assertSame($privateAsset->id, $privateDeduped->id);
            $this->assertSame(2, MediaAsset::query()->count());
        } finally {
            @unlink($tmpPrivate);
            @unlink($tmpPrivate2);
        }
    }

    public function test_upload_same_file_with_different_channel_group_creates_new_asset(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $groupA = \Modules\Identity\Models\ChannelGroup::create(['name' => 'Group A', 'code' => 'group-a']);
        $groupB = \Modules\Identity\Models\ChannelGroup::create(['name' => 'Group B', 'code' => 'group-b']);

        $fileA = $this->pngFile();
        $tmpB = tempnam(sys_get_temp_dir(), 'grp_b_') . '.png';
        copy($fileA->getRealPath(), $tmpB);
        $fileB = new UploadedFile($tmpB, 'group-b.png', 'image/png', null, true);

        $tmpB2 = tempnam(sys_get_temp_dir(), 'grp_b2_') . '.png';
        copy($tmpB, $tmpB2);
        $fileB2 = new UploadedFile($tmpB2, 'group-b-2.png', 'image/png', null, true);

        try {
            $assetA = $action->execute($fileA, ['channel_group_id' => $groupA->id]);
            $assetB = $action->execute($fileB, ['channel_group_id' => $groupB->id]);
            $assetBDeduped = $action->execute($fileB2, ['channel_group_id' => $groupB->id]);

            $this->assertNotSame($assetA->id, $assetB->id);
            $this->assertSame($groupA->id, $assetA->channel_group_id);
            $this->assertSame($groupB->id, $assetB->channel_group_id);
            $this->assertSame($assetB->id, $assetBDeduped->id);
            $this->assertSame(2, MediaAsset::query()->count());
        } finally {
            @unlink($tmpB);
            @unlink($tmpB2);
        }
    }

    public function test_attach_to_private_slot_rolls_back_when_disk_move_fails(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $asset = $action->execute($this->pngFile(), [
            'folder' => 'pub',
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
        ]);
        $post = MediaTestPost::create(['title' => 'Private Slot Post']);

        app(\Modules\Media\Registries\MediaSlotRegistry::class)->register('post.secret_image', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE],
            'maxItems' => 1,
            'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            'label' => 'Ảnh riêng tư',
        ]);

        $this->mock(\Spatie\MediaLibrary\MediaCollections\Filesystem::class, function ($mock) {
            $mock->shouldReceive('copyFromMediaLibrary')
                ->once()
                ->andThrow(new \RuntimeException('Simulated private disk failure'));
        });

        $svc = app(MediaUsageServiceContract::class);

        $caught = false;
        try {
            $svc->attach($post, $asset, 'post.secret_image');
        } catch (\RuntimeException $e) {
            $caught = true;
        }

        $this->assertTrue($caught);
        $fresh = $asset->fresh();
        $this->assertSame(MediaAsset::VISIBILITY_PUBLIC, $fresh->visibility);
        $this->assertSame('media_public', $fresh->mediaFile()?->disk);
        $this->assertSame(0, $svc->count($post, 'post.secret_image'));
    }

    public function test_creating_and_updating_media_asset_does_not_create_slug_records(): void
    {
        $action = new UploadMediaAssetAction(new CalculateMediaChecksumAction(), new ScanMediaSecurityAction());
        $asset = $action->execute($this->pngFile(), [
            'folder' => 'noslug',
            'title' => 'Banner Không Cần Slug',
        ]);

        $asset->update(['title' => 'Banner Đã Đổi Tên']);

        $this->assertSame(0, \Modules\Core\Models\Slug::query()
            ->whereIn('sluggable_type', [MediaAsset::class, $asset->getMorphClass()])
            ->count());
        $this->assertFalse(method_exists($asset, 'slugs'));
    }
}

class MediaTestPost extends Model
{
    use HasThumbnail, HasGallery, HasVideoAsset;
    protected $table = '_test_posts';
    protected $fillable = ['title'];
    protected array $mediaSlots = [
        'thumbnail' => 'post.thumbnail',
        'gallery' => 'post.gallery',
    ];
}

class PlainPostModel extends Model
{
    protected $table = '_test_posts';
    protected $fillable = ['title'];
}