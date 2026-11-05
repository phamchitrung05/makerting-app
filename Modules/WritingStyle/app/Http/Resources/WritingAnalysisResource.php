<?php

namespace Modules\WritingStyle\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Serialize WritingProfileAnalysis cho FE poll trạng thái.
 * =====================================================================
 *
 * Dùng bởi `GET /writing-profiles/analyses/{analysis}` — trang create poll
 * endpoint này sau khi `POST /analyze` trả 202, tới khi status ∈
 * {succeeded, failed}, rồi đọc `result_json` để điền 16 rule card +
 * evidence + style_instructions.
 *
 * KHÔNG trả `reference_text`: văn bản mẫu đã có sẵn ở FE (người dùng vừa
 * dán), trả lại chỉ làm phình payload và lộ nội dung không cần thiết.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - toArray(Request $request): envelope các field trạng thái + kết quả + draft profile.
 * - resultPayload(): chuẩn hóa result_json về 5 khóa cố định (FE khỏi optional-chain).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfileAnalysis (nên eager load `draftProfile`).
 * - OUTPUT: array JSON-serializable.
 * =====================================================================
 */
class WritingAnalysisResource extends JsonResource
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển đổi model WritingProfileAnalysis thành mảng response
     * =====================================================================
     *
     * INPUT:
     * - Request $request: HTTP request hiện tại
     *
     * OUTPUT:
     * - array: Cấu trúc envelope trạng thái phân tích, kết quả rules_json,
     *   evidence_json và quan hệ draftProfile (nếu có)
     *
     * SIDE EFFECT:
     * - Không có (chỉ serialize dữ liệu)
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném exception, không mở transaction
     * =====================================================================
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'source_type'      => $this->source_type,
            'source_url'       => $this->source_url,
            'status'           => $this->status,
            'detected_tier'    => $this->detected_tier,
            'ai_run_id'        => $this->ai_run_id,
            'error_code'       => $this->error_code,
            'error_message'    => $this->error_message,
            'draft_profile_id' => $this->draft_profile_id,
            'result_json'      => $this->resultPayload(),
            // whenLoaded tự trả null khi quan hệ đã load nhưng rỗng (chưa có
            // draft) — không cần tự kiểm tra null trong callback.
            'draft_profile'    => $this->whenLoaded(
                'draftProfile',
                fn () => new WritingProfileResource($this->draftProfile),
            ),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa trường result_json về cấu trúc 5 khóa cố định
     * =====================================================================
     *
     * INPUT:
     * - Không có (đọc trực tiếp từ $this->result_json)
     *
     * OUTPUT:
     * - array|null: Null nếu chưa có kết quả (pending/running); khi đã xong
     *   trả về array đủ 5 khóa (detected_tier, rules_json, evidence_json,
     *   style_instructions, metadata)
     *
     * SIDE EFFECT:
     * - Không có (hàm helper nội bộ)
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném exception, không mở transaction
     * =====================================================================
     */
    protected function resultPayload(): ?array
    {
        $raw = $this->result_json;

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        return [
            'detected_tier'      => $raw['detected_tier'] ?? $this->detected_tier,
            'rules_json'         => is_array($raw['rules_json'] ?? null) ? $raw['rules_json'] : [],
            'evidence_json'      => is_array($raw['evidence_json'] ?? null) ? array_values($raw['evidence_json']) : [],
            'style_instructions' => (string) ($raw['style_instructions'] ?? ''),
            'metadata'           => is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [],
        ];
    }
}
