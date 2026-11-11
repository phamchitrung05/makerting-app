<?php

namespace Modules\WritingStyle\Services;

use Illuminate\Validation\ValidationException;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Resolver một cửa cho Prompt Pack (chuẩn hóa v3/v4/v5)
 * + bộ tiện ích dựng/kiểm tra prompt dùng chung.
 * =====================================================================
 *
 * Mọi code đọc cấu hình prompt PHẢI đi qua class này thay vì đọc trực tiếp
 * `config('writingstyle.v3.*')` rải rác. Nhờ vậy:
 *   - Đổi phiên bản prompt = đổi env `WRITING_STYLE_PROMPT_VERSION`, code
 *     không phải sửa (config map version → file pack trong config.php).
 *   - Năng lực rẽ nhánh bằng `cap()` (đọc `capabilities` của pack), không
 *     phải so sánh cứng `prompt_version === 3`.
 *   - Prompt/lengths/temperature có thể có overlay theo loại nội dung
 *     (`specialties[content_type]`) — deep-merge khi request chỉ định.
 *
 * Khung key pack chuẩn xem `prompts/v3.php` (header file).
 * =====================================================================
 */
final class PromptPack
{
    /** @var array<string, array>|null cache pack đã resolve (đã merge extends). */
    private static ?array $cache = null;

    /** @var array<string, array>|null cache pack THÔ (chưa merge extends). */
    private static ?array $rawCache = null;

    /** @var array<string, bool>|null cache kết quả extends (phòng vòng lặp). */
    private static ?array $extendsChain = null;

    /**
     * =====================================================================
     * CHỨC NĂNG: Khóa config của pack theo số phiên bản prompt hiện hành.
     * =====================================================================
     * INPUT: ?int $version — null = đọc config('writingstyle.prompt_version').
     * OUTPUT: string — key config dưới writingstyle (vd 'v3').
     * SIDE EFFECT: đọc config.
     * =====================================================================
     */
    private static function keyFor(?int $version = null): string
    {
        $v = $version ?? (int) config('writingstyle.prompt_version', 3);

        return 'v'.$v;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Nạp pack thô theo key (không merge extends).
     * =====================================================================
     * INPUT: string $key — 'v3' | 'v4' | ...
     * OUTPUT: array — mảng pack, rỗng nếu chưa đăng ký.
     * SIDE EFFECT: đọc config + ghi self::$cache.
     * =====================================================================
     */
    private static function rawPack(string $key): array
    {
        self::$rawCache ??= [];

        if (! array_key_exists($key, self::$rawCache)) {
            self::$rawCache[$key] = (array) config('writingstyle.'.$key, []);
        }

        return self::$rawCache[$key];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Deep-merge 2 mảng pack — giá trị vô hướng con đè cha,
     * mảng assoc merge đệ quy, mảng list (numeric) con thay hoàn toàn.
     * =====================================================================
     * INPUT: array $base, array $overlay.
     * OUTPUT: array đã merge.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    private static function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])
                && ! array_is_list($value) && ! array_is_list($base[$key])) {
                $base[$key] = self::deepMerge($base[$key], $value);

                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve pack đầy đủ của 1 phiên bản — merge chuỗi extends
     * (cha trước, con sau) và cache theo key.
     * =====================================================================
     * INPUT: ?int $version — null = phiên bản prompt hiện hành.
     * OUTPUT: array pack đã merge extends.
     * SIDE EFFECT: đọc config + ghi self::$cache/self::$extendsChain.
     * EXCEPTION: \RuntimeException khi chuỗi extends tạo vòng lặp.
     * =====================================================================
     */
    public static function resolve(?int $version = null): array
    {
        $key = self::keyFor($version);

        self::$cache ??= [];
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        self::$extendsChain ??= [];
        if (isset(self::$extendsChain[$key])) {
            throw new \RuntimeException("Prompt pack '{$key}' tạo vòng lặp extends.");
        }
        self::$extendsChain[$key] = true;

        $pack = self::rawPack($key);
        $parentKey = $pack['extends'] ?? null;

        if (is_string($parentKey) && $parentKey !== '' && $parentKey !== $key) {
            $parent = self::resolveVersion($parentKey);
            $pack = self::deepMerge($parent, $pack);
        }

        unset(self::$extendsChain[$key]);
        self::$cache[$key] = $pack;

        return $pack;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve pack theo TÊN key ('v3') — dùng bởi extends.
     * =====================================================================
     * INPUT: string $key — tên key pack cha.
     * OUTPUT: array pack cha đã resolve.
     * SIDE EFFECT: như resolve().
     * =====================================================================
     */
    private static function resolveVersion(string $key): array
    {
        // extends khai theo key ('v3'), không theo số — gọi raw + merge.
        self::$cache ??= [];

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        self::$extendsChain ??= [];
        if (isset(self::$extendsChain[$key])) {
            throw new \RuntimeException("Prompt pack '{$key}' tạo vòng lặp extends.");
        }
        self::$extendsChain[$key] = true;

        $pack = self::rawPack($key);
        $parentKey = $pack['extends'] ?? null;

        if (is_string($parentKey) && $parentKey !== '' && $parentKey !== $key) {
            $parent = self::resolveVersion($parentKey);
            $pack = self::deepMerge($parent, $pack);
        }

        unset(self::$extendsChain[$key]);
        self::$cache[$key] = $pack;

        return $pack;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Pack đã áp overlay specialty (nếu có) cho content_type.
     * =====================================================================
     * INPUT: ?string $contentType — content_type từ brief (vd 'facebook_status').
     * OUTPUT: array pack + specialty merged (specialty đè base).
     * SIDE EFFECT: không ghi thêm cache (resolve đã cache base).
     * =====================================================================
     */
    public static function pack(?string $contentType = null): array
    {
        $pack = self::resolve();

        if ($contentType === null || $contentType === '') {
            return $pack;
        }

        $specialty = $pack['specialties'][$contentType] ?? null;
        if (! is_array($specialty) || $specialty === []) {
            return $pack;
        }

        return self::deepMerge($pack, $specialty);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Phiên bản prompt hiện hành (số nguyên).
     * =====================================================================
     * OUTPUT: int — giá trị config('writingstyle.prompt_version').
     * =====================================================================
     */
    public static function version(): int
    {
        return (int) config('writingstyle.prompt_version', 3);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra pack hiện hành có năng lực chỉ định không.
     * =====================================================================
     * Thay cho các nhánh cứng kiểu `PromptV3::enabled()`. Ví dụ:
     *   PromptPack::cap('brief')         // writer/planner nhận brief
     *   PromptPack::cap('quality_report')
     *
     * INPUT: string $name — tên capability.
     * OUTPUT: bool.
     * =====================================================================
     */
    public static function cap(string $name): bool
    {
        return (bool) (self::resolve()['capabilities'][$name] ?? false);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: System prompt của 1 vai trò, có áp specialty theo content_type.
     * =====================================================================
     * INPUT: string $role — 'analyzer'|'compiler'|'planner'|'writer'.
     *        ?string $contentType — content_type brief (overlay specialties).
     * OUTPUT: string — prompt (rỗng nếu vai trò không khai báo).
     * =====================================================================
     */
    public static function role(string $role, ?string $contentType = null): string
    {
        return (string) (self::pack($contentType)['prompts'][$role] ?? '');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giá trị giới hạn (limits) của pack hiện hành.
     * =====================================================================
     * INPUT: string $name (vd 'max_context_chars'), giá trị mặc định.
     * OUTPUT: mixed.
     * =====================================================================
     */
    public static function limit(string $name, mixed $default = null): mixed
    {
        return self::resolve()['limits'][$name] ?? $default;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Nhiệt độ mặc định theo vai trò (temperatures).
     * =====================================================================
     * INPUT: string $role — 'analyzer'|'planner'|'writer'.
     * OUTPUT: float.
     * =====================================================================
     */
    public static function temperature(string $role, float $default = 0.7): float
    {
        return (float) (self::resolve()['temperatures'][$role] ?? $default);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Từ điển của pack (lengths/questions/axes/presets...).
     * =====================================================================
     * INPUT: string $name — key từ điển; ?string $contentType cho overlay.
     * OUTPUT: mixed — mảng từ điển hoặc null.
     * =====================================================================
     */
    public static function dict(string $name, ?string $contentType = null): mixed
    {
        return self::pack($contentType)[$name] ?? null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa cache pack (dùng trong test khi đổi config runtime).
     * =====================================================================
     * OUTPUT: void. SIDE EFFECT: reset self::$cache + $extendsChain.
     * =====================================================================
     */
    public static function flush(): void
    {
        self::$cache = null;
        self::$rawCache = null;
        self::$extendsChain = null;
    }

    /* =====================================================================
     * BỘ TIỆN ÍCH DỰNG & KIỂM TRA PROMPT (từ PromptV3 cũ — giữ nguyên hành vi)
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Dựng JSON Schema object strict — mọi property đều required
     * + additionalProperties:false (chuẩn OpenAI strict json_schema).
     * =====================================================================
     * INPUT: array $fields — map tên → định nghĩa property.
     * OUTPUT: array schema object.
     * =====================================================================
     */
    public static function objectSchema(array $fields): array
    {
        return [
            'type' => 'object',
            'properties' => $fields,
            'required' => array_keys($fields),
            'additionalProperties' => false,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mã hóa JSON an toàn — không escape unicode + chặn tag HTML
     * (JSON_THROW_ON_ERROR để lỗi encode thành exception rõ ràng).
     * =====================================================================
     * INPUT: mixed $value.
     * OUTPUT: string JSON.
     * EXCEPTION: JsonException khi giá trị không encode được.
     * =====================================================================
     */
    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chặn đầu vào quá dài trước khi gọi AI (bảo vệ context).
     * =====================================================================
     * INPUT: string ...$parts — các phần prompt/system cần cộng độ dài.
     * OUTPUT: void.
     * EXCEPTION: ValidationException (422) khi vượt limits.max_context_chars.
     * =====================================================================
     */
    public static function checkContext(string ...$parts): void
    {
        if (array_sum(array_map('mb_strlen', $parts)) > (int) self::limit('max_context_chars', 40000)) {
            throw ValidationException::withMessages(['brief' => 'Đầu vào quá dài. Hãy rút gọn bài mẫu, hồ sơ hoặc brief.']);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Bảo đảm AI hoàn tất đầu ra (finish_reason hợp lệ) — chặn
     * bài bị cắt giữa chừng do giới hạn token bị coi là bài hoàn chỉnh.
     * =====================================================================
     * INPUT: AiStructuredResponse $response.
     * OUTPUT: void — pass khi finish_reason null (driver không trả) hoặc thuộc
     *         tập kết thúc sạch {stop, end_turn, stop_sequence, tool_use, STOP}.
     * EXCEPTION: InvalidPipelineException khi bị cắt vì lý do khác.
     * =====================================================================
     */
    public static function requireComplete(AiStructuredResponse $response): void
    {
        $reason = $response->raw['choices'][0]['finish_reason']
            ?? $response->raw['stop_reason']
            ?? $response->raw['candidates'][0]['finishReason']
            ?? null;

        if ($reason !== null && ! in_array($reason, ['stop', 'end_turn', 'stop_sequence', 'tool_use', 'STOP'], true)) {
            throw new InvalidPipelineException('AI chưa hoàn thành đầu ra. Hãy kiểm tra giới hạn hoặc thử lại.');
        }
    }
}
