<?php

namespace Modules\WritingStyle\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Services\WritingBriefSuggestService;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử WritingBriefSuggestService (sinh brief từ AI).
 * =====================================================================
 *
 * Các ca:
 *   1. suggest() trả brief chuẩn hóa từ output AI hợp lệ.
 *   2. provided_facts được đánh lại ID fact_01..fact_N (không tin ID AI).
 *   3. Reject khi AI trả content_type ngoài enum.
 *   4. Chặn topic rỗng / AI trả data rỗng / AI bị cắt (requireComplete).
 * =====================================================================
 */
class WritingBriefSuggestServiceTest extends TestCase
{
    private function profile(): WritingProfile
    {
        $p = new WritingProfile;
        $p->id = 1;
        $p->name = 'Du Lịch Đà Lạt';
        $p->dominant_tier = WritingProfile::TIER_MEDIUM;
        $p->style_instructions = 'Giọng thủ thỉ, gần gũi.';

        return $p;
    }

    private function mockAi(array $responseData, ?array $raw = null): CoreAiGatewayContract
    {
        $ai = $this->mock(CoreAiGatewayContract::class);
        $ai->shouldReceive('lastRun')->andReturn(null);
        $ai->shouldReceive('executeStructured')
            ->once()
            ->withArgs(fn (AiStructuredRequest $request): bool => $request->operation === 'sandbox_brief_suggest')
            ->andReturn(new AiStructuredResponse(
                text: '',
                data: $responseData,
                raw: $raw ?? ['choices' => [['finish_reason' => 'stop']]],
            ));

        return $ai;
    }

    /** Output AI mẫu hợp lệ. */
    private function validAiOutput(): array
    {
        return [
            'content_type' => 'blog',
            'platform' => 'Facebook',
            'audience' => 'Người yêu du lịch miền Trung',
            'objective' => 'Giới thiệu Đà Nẵng như điểm đến vui nhộn',
            'offer' => null,
            'cta' => 'Lên kế hoạch khám phá Đà Nẵng ngay',
            'provided_facts' => [
                ['text' => 'Đà Nẵng là thành phố biển ở miền Trung Việt Nam'],
                ['text' => 'Cầu Rồng phun lửa cuối tuần'],
            ],
            'format' => ['headings' => true, 'bullets' => true],
            'constraints' => ['Không nêu giá vé cụ thể'],
            'warnings' => [],
        ];
    }

    // -----------------------------------------------------------------
    // 1. Trả brief chuẩn hóa từ output AI hợp lệ.
    // -----------------------------------------------------------------
    public function test_suggest_tra_brief_chuan_hoa_tu_output_ai(): void
    {
        $service = new WritingBriefSuggestService($this->mockAi($this->validAiOutput()));
        $brief = $service->suggest($this->profile(), 'Đà Nẵng, thành phố vui nhộn thời đại mới', 'long');

        $this->assertSame('blog', $brief['content_type']);
        $this->assertSame('Facebook', $brief['platform']);
        $this->assertSame('Người yêu du lịch miền Trung', $brief['audience']);
        $this->assertSame('Lên kế hoạch khám phá Đà Nẵng ngay', $brief['cta']);
        $this->assertSame(['headings' => true, 'bullets' => true], $brief['format']);
        $this->assertSame(['Không nêu giá vé cụ thể'], $brief['constraints']);
    }

    // -----------------------------------------------------------------
    // 2. provided_facts được đánh lại ID fact_01..fact_N.
    // -----------------------------------------------------------------
    public function test_facts_duoc_danh_lai_id_tuan_tu(): void
    {
        $service = new WritingBriefSuggestService($this->mockAi($this->validAiOutput()));
        $brief = $service->suggest($this->profile(), 'Chủ đề X', 'medium');

        $ids = array_column($brief['provided_facts'], 'id');
        $this->assertSame(['fact_01', 'fact_02'], $ids);
        $this->assertSame(
            ['user', 'user'],
            array_column($brief['provided_facts'], 'source'),
        );
        $this->assertSame('Đà Nẵng là thành phố biển ở miền Trung Việt Nam', $brief['provided_facts'][0]['text']);
    }

    // -----------------------------------------------------------------
    // 3. Reject khi AI trả content_type ngoài enum.
    // -----------------------------------------------------------------
    public function test_reject_content_type_ngoai_enum(): void
    {
        $data = $this->validAiOutput();
        $data['content_type'] = 'vlog';

        $service = new WritingBriefSuggestService($this->mockAi($data));
        $this->expectException(ValidationException::class);
        $service->suggest($this->profile(), 'Chủ đề', 'medium');
    }

    // -----------------------------------------------------------------
    // 4. Chặn topic rỗng / data rỗng / AI bị cắt.
    // -----------------------------------------------------------------
    public function test_chan_topic_rong(): void
    {
        $ai = $this->mock(CoreAiGatewayContract::class);
        $ai->shouldNotReceive('executeStructured'); // không được gọi khi topic rỗng
        $service = new WritingBriefSuggestService($ai);

        $this->expectException(ValidationException::class);
        $service->suggest($this->profile(), '   ', 'medium');
    }

    public function test_chan_data_ai_rong(): void
    {
        $service = new WritingBriefSuggestService($this->mockAi([]));
        $this->expectException(InvalidPipelineException::class);
        $service->suggest($this->profile(), 'Chủ đề', 'medium');
    }

    public function test_chan_ai_bi_cat(): void
    {
        $ai = $this->mockAi($this->validAiOutput(), ['choices' => [['finish_reason' => 'length']]]);
        $service = new WritingBriefSuggestService($ai);
        $this->expectException(InvalidPipelineException::class);
        $service->suggest($this->profile(), 'Chủ đề', 'medium');
    }

    // -----------------------------------------------------------------
    // 5. Format null theo hồ sơ được giữ nguyên (không bóc thành false).
    // -----------------------------------------------------------------
    public function test_format_null_theo_ho_so(): void
    {
        $data = $this->validAiOutput();
        $data['format'] = ['headings' => null, 'bullets' => null];

        $service = new WritingBriefSuggestService($this->mockAi($data));
        $brief = $service->suggest($this->profile(), 'Chủ đề', 'medium');

        // format bị lọc hết key null -> rỗng (không phải ['headings'=>false]).
        $this->assertSame([], $brief['format']);
    }

    // -----------------------------------------------------------------
    // 6. Constraint độ dài do AI nhắc bị lọc bỏ (không giữ "1200 từ" cố định).
    // -----------------------------------------------------------------
    public function test_constraints_do_dai_bi_loc_bo(): void
    {
        $data = $this->validAiOutput();
        $data['constraints'] = [
            'Không đề cập giá vé cụ thể',
            'Độ dài tối đa 1200 từ',       // nói về độ dài -> phải lọc bỏ
            'Bài viết trong khoảng 800-1200 từ', // nói về độ dài -> phải lọc bỏ
            'Tránh thuật ngữ chuyên môn',
        ];

        $service = new WritingBriefSuggestService($this->mockAi($data));
        $brief = $service->suggest($this->profile(), 'Chủ đề', 'long');

        $this->assertSame(['Không đề cập giá vé cụ thể', 'Tránh thuật ngữ chuyên môn'], $brief['constraints']);
    }
}
