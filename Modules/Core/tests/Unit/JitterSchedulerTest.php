<?php

namespace Modules\Core\Tests\Unit;

use Illuminate\Support\Carbon;
use Modules\Core\Services\JitterScheduler;
use PHPUnit\Framework\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test JitterScheduler (rải lịch, min gap).
 * =====================================================================
 */
class JitterSchedulerTest extends TestCase
{
    public function test_schedule_cong_jitter_trong_gioi_han(): void
    {
        $s = new JitterScheduler();
        $base = Carbon::create(2026, 1, 1, 12, 0, 0);
        $result = $s->schedule($base, 60);

        $diff = $result->getTimestamp() - $base->getTimestamp();
        $this->assertGreaterThanOrEqual(0, $diff);
        $this->assertLessThanOrEqual(60, $diff);
    }

    public function test_spread_tra_so_luong_va_tang_dan(): void
    {
        $s = new JitterScheduler();
        $base = Carbon::create(2026, 1, 1, 12, 0, 0);
        $times = $s->spread($base, 5, 3600, 30);

        $this->assertCount(5, $times);
        for ($i = 1; $i < 5; $i++) {
            $this->assertGreaterThanOrEqual(
                $times[$i - 1]->getTimestamp() + 30,
                $times[$i]->getTimestamp()
            );
        }
    }

    public function test_delay_trong_gioi_han(): void
    {
        $s = new JitterScheduler();
        $d = $s->delay(120);
        $this->assertGreaterThanOrEqual(0, $d);
        $this->assertLessThanOrEqual(120, $d);
    }
}
