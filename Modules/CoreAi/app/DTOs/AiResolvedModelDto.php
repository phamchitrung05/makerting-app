<?php

namespace Modules\CoreAi\DTOs;

use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO cặp provider+model đã được ModelResolver chọn.
 * =====================================================================
 *
 * Trả về từ `ModelResolver::resolve()` — gói `AiProvider` + `AiModel` đã
 * chọn cùng `apiKey` (plaintext) để AiProviderClient gọi HTTP mà không
 * phải giải mã lại. `keyIndex` ghi index key đã dùng trong
 * provider.allKeys() (xoay vòng) cho diagnostics.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiProvider, AiModel, apiKey plaintext, keyIndex.
 * - OUTPUT: readonly object → driver dùng để gọi HTTP.
 * =====================================================================
 */
final readonly class AiResolvedModelDto
{
    /**
     * CHỨC NĂNG: khởi tạo cặp đã resolve.
     * INPUT: provider, model, apiKey, keyIndex.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public AiProvider $provider,
        public AiModel $model,
        public string $apiKey,
        public int $keyIndex = 0,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Snapshot an toàn ghi vào ai_runs.connection_snapshot_json.
     * INPUT: không. OUTPUT: array — KHÔNG chứa apiKey.
     * =====================================================================
     */
    public function connectionSnapshot(): array
    {
        return array_merge(
            $this->provider->connectionSnapshot($this->model),
            ['key_index' => $this->keyIndex]
        );
    }
}