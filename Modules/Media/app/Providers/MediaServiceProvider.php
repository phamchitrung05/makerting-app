<?php

namespace Modules\Media\Providers;

use Modules\Identity\Registries\PermissionRegistry;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Registries\MediaSlotRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Media (Tầng 1).
 * =====================================================================
 *
 * Quản lý media 2 lớp: metadata MediaAsset + file vật lý Spatie MediaLibrary
 * (collection 'library'). Cung cấp MediaSlotRegistry cho các module đăng ký
 * slot media + HasMediaSlots/HasThumbnail/HasGallery/HasVideoAsset traits.
 *
 * - register(): singleton MediaSlotRegistry.
 * - boot()    : đăng ký slot nền + nhóm quyền 'media' vào PermissionRegistry.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): binding container.
 * - boot()    : seed registry + permission + slot mặc định.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình module từ module.json.
 * - OUTPUT: binding + registry đã nạp slot nền + quyền.
 * =====================================================================
 */
class MediaServiceProvider extends ModuleServiceProvider
{
    /** The name of the module. */
    protected string $name = 'Media';

    /** The lowercase version of the module name. */
    protected string $nameLower = 'media';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký binding container của module
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: singleton MediaSlotRegistry.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(MediaSlotRegistry::class, fn () => new MediaSlotRegistry());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Boot module — đăng ký slot nền + quyền media
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT:
     * - MediaSlotRegistry nhận các slot mặc định của module nghiệp vụ
     *   (thumbnail, gallery, video_asset + một số slot dùng sẵn).
     * - PermissionRegistry thêm 'media.view'/'media.manage'.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        /** @var MediaSlotRegistry $slots */
        $slots = $this->app->make(MediaSlotRegistry::class);

        // Slot mặc định hệ thống — model dùng khi không tự khai báo.
        $slots->register('thumbnail', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE],
            'maxItems' => 1,
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            'label' => 'Ảnh đại diện',
        ]);

        $slots->register('gallery', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE, MediaAsset::KIND_VIDEO],
            'maxItems' => 0, // không giới hạn
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            'label' => 'Gallery',
        ]);

        $slots->register('video', [
            'allowedKinds' => [MediaAsset::KIND_VIDEO],
            'maxItems' => 1,
            'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            'label' => 'Video',
        ]);

        // Slot cho các domain module (dùng sẵn; chúng tự register thêm nếu cần).
        $slots->register('post.thumbnail', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE],
            'maxItems' => 1,
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            'label' => 'Thumbnail bài viết',
        ]);

        $slots->register('post.gallery', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE],
            'maxItems' => 0,
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            'label' => 'Gallery bài viết',
        ]);

        $slots->register('social_variant.media', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE, MediaAsset::KIND_VIDEO],
            'maxItems' => 0,
            'visibility' => MediaAsset::VISIBILITY_PUBLIC,
            'label' => 'Media biến thể mạng xã hội',
        ]);

        $slots->register('video_project.output', [
            'allowedKinds' => [MediaAsset::KIND_VIDEO],
            'maxItems' => 1,
            'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            'label' => 'Video kết xuất',
        ]);

        $slots->register('visual_style.reference', [
            'allowedKinds' => [MediaAsset::KIND_IMAGE],
            'maxItems' => 0,
            'visibility' => MediaAsset::VISIBILITY_PRIVATE,
            'label' => 'Ảnh tham chiếu DNA',
        ]);

        /** @var PermissionRegistry $permissions */
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->registerModule('media', [
            'label' => 'Quản lý Media',
            'permissions' => [
                'media.view' => 'Xem thư viện media',
                'media.manage' => 'Tải lên/sửa/xóa media',
            ],
        ]);
    }
}
