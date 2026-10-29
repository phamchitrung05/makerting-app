<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Traits\HasAuditTrail;
use Modules\Identity\Models\User;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test HasAuditTrail tự điền created_by/updated_by an toàn.
 * =====================================================================
 * Kiểm chứng:
 * - Gán created_by + updated_by khi tạo (nếu cột tồn tại).
 * - Gán updated_by khi update (nếu cột tồn tại).
 * - Bỏ qua cột không có trong schema — KHÔNG ném SQL error.
 * - Không ghi đè created_by đã set tay.
 * - Bỏ qua khi không có user đăng nhập.
 * =====================================================================
 */
class HasAuditTrailTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_both', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_created_only', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_none', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_both');
        Schema::dropIfExists('audit_created_only');
        Schema::dropIfExists('audit_none');
        parent::tearDown();
    }

    /**
     * CHỨC NĂNG: Tạo user đăng nhập giả và trả về id.
     * INPUT : không có. OUTPUT: int user_id.
     */
    private function loginUser(): int
    {
        $user = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('x'),
        ]);
        Auth::login($user);

        return $user->id;
    }

    public function test_dien_created_by_va_updated_by_khi_tao(): void
    {
        $userId = $this->loginUser();

        $m = AuditBoth::create(['name' => 'A']);
        $this->assertSame($userId, $m->created_by);
        $this->assertSame($userId, $m->updated_by);
    }

    public function test_dien_updated_by_khi_update(): void
    {
        $userId = $this->loginUser();

        $m = AuditBoth::create(['name' => 'A']);
        $other = User::forceCreate([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('x'),
        ]);
        Auth::login($other);

        $m->update(['name' => 'B']);
        $this->assertSame($other->id, $m->updated_by);
        $this->assertSame($userId, $m->created_by); // created_by giữ nguyên
    }

    public function test_khong_loi_khi_bang_chi_co_created_by(): void
    {
        $userId = $this->loginUser();

        // Trước fix: SQL error "Unknown column 'updated_by'" khi update.
        $m = AuditCreatedOnly::create(['name' => 'A']);
        $this->assertSame($userId, $m->created_by);

        $m->update(['name' => 'B']);
        $this->assertSame($userId, $m->created_by);
        $this->assertNull($m->getAttribute('updated_by')); // không tồn tại
    }

    public function test_khong_loi_khi_bang_khong_co_cot_audit(): void
    {
        $this->loginUser();

        // Bảng không có cả created_by lẫn updated_by — phải im lặng bỏ qua.
        $m = AuditNone::create(['name' => 'A']);
        $m->update(['name' => 'B']);
        $this->assertNull($m->getAttribute('created_by'));
        $this->assertNull($m->getAttribute('updated_by'));
    }

    public function test_khong_ghi_de_created_by_da_set_tay(): void
    {
        $this->loginUser();

        $m = AuditBoth::create(['name' => 'A', 'created_by' => 999]);
        $this->assertSame(999, $m->created_by);
    }

    public function test_bo_qua_khi_khong_co_user(): void
    {
        Auth::logout();

        $m = AuditBoth::create(['name' => 'A']);
        $this->assertNull($m->created_by);
        $this->assertNull($m->updated_by);
    }
}

class AuditBoth extends Model
{
    use HasAuditTrail;

    protected $table = 'audit_both';
    protected $fillable = ['name', 'created_by', 'updated_by'];
}

class AuditCreatedOnly extends Model
{
    use HasAuditTrail;

    protected $table = 'audit_created_only';
    protected $fillable = ['name', 'created_by'];
}

class AuditNone extends Model
{
    use HasAuditTrail;

    protected $table = 'audit_none';
    protected $fillable = ['name'];
}
