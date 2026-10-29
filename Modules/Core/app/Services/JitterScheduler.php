<?php

namespace Modules\Core\Services;

use Illuminate\Support\Carbon;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Rải lệch lịch thực thi (jitter) để tránh bị quét spam.
 * =====================================================================
 * Khi lên lịch hàng loạt tài khoản mạng xã hội đăng cùng khung giờ, cộng
 * thêm độ trễ ngẫu nhiên có kiểm soát vào mỗi tác vụ để các request tới
 * Facebook/YouTube/TikTok không dồn về một thời điểm (dễ bị flag).
 * =====================================================================
 */
class JitterScheduler
{
    /**
     * Cộng jitter ngẫu nhiên (0..maxJitterSeconds) vào một mốc thời gian.
     * INPUT: base (Carbon), maxJitterSeconds (int).
     * OUTPUT: Carbon — thời điểm sau khi cộng jitter.
     * SIDE EFFECT: không có.
     */
    public function schedule(Carbon $base, int $maxJitterSeconds = 300): Carbon
    {
        $jitter = random_int(0, max(0, $maxJitterSeconds));

        return $base->copy()->addSeconds($jitter);
    }

    /**
     * Rải N tác vụ vào một cửa sổ thời gian, mỗi tác vụ lệch ngẫu nhiên.
     * INPUT: base (Carbon), count, windowSeconds, minGapSeconds.
     * OUTPUT: Carbon[] danh sách mốc thời gian đã rải (tăng dần).
     * SIDE EFFECT: không có.
     */
    public function spread(Carbon $base, int $count, int $windowSeconds = 3600, int $minGapSeconds = 0): array
    {
        if ($count <= 0) {
            return [];
        }

        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $offset = $windowSeconds > 0 ? random_int(0, $windowSeconds) : 0;
            $times[] = $base->copy()->addSeconds($offset);
        }

        usort($times, fn (Carbon $a, Carbon $b): int => $a->getTimestamp() <=> $b->getTimestamp());

        if ($minGapSeconds > 0) {
            $times = $this->enforceMinGap($times, $minGapSeconds);
        }

        return $times;
    }

    /**
     * Đảm bảo hai mốc liên tiếp cách nhau tối thiểu minGap giây.
     * INPUT: Carbon[] đã sort tăng dần, minGap.
     * OUTPUT: Carbon[] đã điều chỉnh.
     * SIDE EFFECT: không có (clone trước khi sửa).
     */
    protected function enforceMinGap(array $times, int $minGap): array
    {
        $result = [];
        foreach ($times as $t) {
            $t = $t->copy();
            $prev = end($result);
            if ($prev !== false && $t->lessThan($prev->copy()->addSeconds($minGap))) {
                $t = $prev->copy()->addSeconds($minGap);
            }
            $result[] = $t;
        }

        return $result;
    }

    /**
     * Tạo độ trễ jitter (giây) để truyền vào dispatch()->delay().
     * INPUT: maxJitterSeconds. OUTPUT: int giây.
     * SIDE EFFECT: không có.
     */
    public function delay(int $maxJitterSeconds = 300): int
    {
        return random_int(0, max(0, $maxJitterSeconds));
    }
}
