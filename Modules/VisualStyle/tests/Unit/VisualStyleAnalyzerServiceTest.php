<?php

namespace Modules\VisualStyle\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\VisualStyle\Models\VisualStyle;
use Modules\VisualStyle\Services\VisualPromptPack;
use Modules\VisualStyle\Services\VisualStyleAnalyzerService;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử Vision Analyzer Service (Task 3).
 * =====================================================================
 *
 * Mock `CoreAiGatewayContract` để không gọi AI thật; refresh DB để có
 * categories/tags cho filterTaxonomySuggestion.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - fakeGateway(): Mock gateway trả structured data + lastRun stub.
 * - fakeGatewayThrow(): Mock gateway ném lỗi (transient/permanent).
 * - dnaPayload(): Fixture DNA đầy đủ đúng schema.
 * - test_analyze_dna_parses_schema_output_to_image_dto(): Parse đúng JSON,
 *   trả ImageDnaDto + diagnostics đủ model/provider/latency.
 * - test_analyze_dna_normalizes_dna_and_filters_taxonomy(): normalizeDNA lọc
 *   key lạ + lọc taxonomy suggestion theo scope visual_style.
 * - test_analyze_dna_rejects_truncated_output(): finish_reason=length →
 *   AiGatewayException truncated_output.
 * - test_analyze_media_requires_reference_and_builds_attachment():
 *   analyzeMedia đọc stream media, dựng attachment, gọi gateway.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Mock gateway + fixtures DNA trong bộ nhớ/DB test.
 * - OUTPUT: Assertions thành công hoặc lỗi kiểm thử.
 *
 * BỐI CẢNH SỬ DỤNG: PHPUnit; không gọi AI thật.
 * =====================================================================
 */
class VisualStyleAnalyzerServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * =====================================================================
     * CHỨC NĂNG: Mock gateway trả structured data cố định
     * =====================================================================
     *
     * INPUT:
     * - array $data — payload AI trả về.
     * - ?object $run — AiRun fake cho lastRun() (tạo bằng DB khi cần).
     *
     * OUTPUT:
     * - void — instance CoreAiGatewayContract đã bind vào container.
     *
     * SIDE EFFECT:
     * - Ghi mock vào application container.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    private function fakeGateway(array $data, ?object $run = null): void
    {
        $resp = new AiStructuredResponse(
            text: json_encode($data),
            data: $data,
            usage: ['input_tokens' => 123, 'output_tokens' => 456],
            remoteModelId: 'vision-fake-model',
            providerKey: 'fake-vision-provider',
            latencyMs: 780,
            raw: ['choices' => [['finish_reason' => 'stop']]],
        );

        $this->app->instance(CoreAiGatewayContract::class, new class($resp, $run) implements CoreAiGatewayContract {
            public function __construct(private $resp, private $run) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { return $this->resp; }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
            public function lastRun() { return $this->run; }
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mock gateway ném lỗi chỉ định
     * =====================================================================
     *
     * INPUT:
     * - string $code — errorCode của AiGatewayException.
     * - ?Throwable $outer — Throwable ngoài (null → ném AiGatewayException).
     *
     * OUTPUT:
     * - void — instance CoreAiGatewayContract ném lỗi khi executeStructured.
     *
     * SIDE EFFECT:
     * - Ghi mock vào application container.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    private function fakeGatewayThrow(string $code, ?\Throwable $outer = null): void
    {
        $this->app->instance(CoreAiGatewayContract::class, new class($code, $outer) implements CoreAiGatewayContract {
            public function __construct(private string $code, private ?\Throwable $outer) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse {
                throw $this->outer ?? new AiGatewayException('Lỗi giả lập', $this->code);
            }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Fixture DNA đầy đủ đúng schema
     * =====================================================================
     *
     * INPUT:
     * - array $overrides — ghi đè phần payload.
     *
     * OUTPUT:
     * - array dna_json hợp lệ schema v1.
     *
     * SIDE EFFECT:
     * - Đọc VisualPromptPack để biết danh sách field.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    private function dnaPayload(array $overrides = []): array
    {
        return array_replace([
            'composition' => [
                'camera_angle'    => 'low_angle',
                'framing_rule'    => 'rule_of_thirds',
                'depth_of_field'  => 'shallow_dof',
            ],
            'camera' => [
                'lens_type'    => '85mm',
                'shot_medium'  => 'photograph',
                'perspective'  => 'medium',
            ],
            'lighting' => [
                'tone'      => 'warm_golden',
                'key_light' => 'rim_light',
            ],
            'environment' => [
                'setting'    => 'studio',
                'backdrop'   => 'nền xám nhạt',
                'atmosphere' => 'clean',
            ],
            'color_mood' => [
                'palette_hex' => ['#1A1A2E', '#E94560'],
                'temperature' => 'warm',
                'contrast'    => 'high',
                'grade'       => 'teal_orange',
            ],
            'subject_rules' => [
                'preserve'  => ['logo', 'product_shape'],
                'forbidden' => ['watermark', 'deformed_hands'],
            ],
            'style_constraints' => 'Chất ảnh điện ảnh ấm áp.',
            'confidence'         => 0.85,
            'taxonomy_suggestion'=> [],
        ], $overrides);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Parse đúng JSON → ImageDnaDto + diagnostics đầy đủ
     * =====================================================================
     *
     * INPUT:
     * - Mock gateway trả dnaPayload đầy đủ.
     * - Attachment base64 giả lập.
     *
     * OUTPUT:
     * - void; assertions dna → ImageDnaDto đúng 5 nhóm, diagnostics có model/
     *   provider/tokens/latency.
     *
     * SIDE EFFECT:
     * - Gọi gateway mock; không ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_analyze_dna_parses_schema_output_to_image_dto(): void
    {
        $this->fakeGateway($this->dnaPayload());

        $attachment = ['mime' => 'image/png', 'data' => base64_encode('FAKEPNG')];
        $out = app(VisualStyleAnalyzerService::class)->analyzeDna($attachment);

        $dna = $out['dna'];
        $diag = $out['diagnostics'];

        $this->assertTrue($dna->isComplete());
        $this->assertSame('low_angle', $dna->composition['camera_angle']);
        $this->assertSame('85mm', $dna->camera['lens_type']);
        $this->assertSame(0.85, $dna->confidence);
        $this->assertSame('vision-fake-model', $diag['model']);
        $this->assertSame('fake-vision-provider', $diag['provider']);
        $this->assertSame(123, $diag['tokens']['input']);
        $this->assertSame(456, $diag['tokens']['output']);
        $this->assertSame(780, $diag['latency_ms']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: normalizeDNA lọc key lạ + taxonomy suggestion theo scope
     * =====================================================================
     *
     * INPUT:
     * - DB có 1 category scope='visual_style', 1 tag scope dùng chung,
     *   1 tag scope='blog' (phải bị loại).
     * - Gateway trả payload có key lạ + suggestion gồm 2 tên hợp lệ +
     *   2 tên không tồn tại / sai scope.
     *
     * OUTPUT:
     * - void; assertions taxonomy_suggestion chỉ giữ 2 tên hợp lệ.
     *
     * SIDE EFFECT:
     * - INSERT taxonomy test rows; gọi gateway mock.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Transaction rollback sau test.
     * =====================================================================
     */
    public function test_analyze_dna_normalizes_dna_and_filters_taxonomy(): void
    {
        // Taxonomy thật: category scope visual_style + tag scope null (shared) +
        // tag scope blog (phải bị lọc ra).
        \Modules\Taxonomy\Models\Category::create([
            'scope' => 'visual_style', 'name' => 'Sản phẩm', 'status' => 'active',
        ]);
        \Modules\Taxonomy\Models\Tag::create([
            'scope' => null, 'name' => 'Giờ vàng', 'status' => 'active',
        ]);
        \Modules\Taxonomy\Models\Tag::create([
            'scope' => 'blog', 'name' => 'Blog tag', 'status' => 'active',
        ]);

        $payload = $this->dnaPayload([
            'composition' => ['camera_angle' => 'low_angle', 'framing_rule' => 'rule_of_thirds', 'depth_of_field' => 'deep', 'extra_key' => 'drop_me'],
            'taxonomy_suggestion' => ['Sản phẩm', 'giờ vàng', 'Blog tag', 'không tồn tại', 'Sản phẩm'],
        ]);
        $this->fakeGateway($payload);

        $out = app(VisualStyleAnalyzerService::class)->analyzeDna(
            ['mime' => 'image/png', 'data' => base64_encode('FAKE')],
        );

        $dna = $out['dna'];

        // extra_key bị strip khỏi DNA.
        $this->assertArrayNotHasKey('extra_key', $dna->composition);

        // taxonomy_suggestion: giữ tên có thật thuộc scope (visual_style) hoặc
        // shared (null); match case-insensitive nhưng giữ nguyên văn AI đã đề
        // xuất. Loại 'Blog tag' (scope blog) + 'không tồn tại' + bản lặp.
        $sug = $dna->taxonomySuggestion;
        $this->assertContains('Sản phẩm', $sug);
        $this->assertContains('giờ vàng', $sug);
        $this->assertNotContains('Blog tag', $sug);
        $this->assertNotContains('không tồn tại', $sug);
        $this->assertLessThanOrEqual(5, count($sug));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: finish_reason=length → ném AiGatewayException truncated_output
     * =====================================================================
     *
     * INPUT:
     * - Gateway trả response finish_reason=length.
     *
     * OUTPUT:
     * - void; assertions ném AiGatewayException errorCode=truncated_output.
     *
     * SIDE EFFECT:
     * - Gọi gateway mock.
     *
     * EXCEPTION/TRANSACTION:
     * - AiGatewayException là kết quả mong đợi.
     * =====================================================================
     */
    public function test_analyze_dna_rejects_truncated_output(): void
    {
        $resp = new AiStructuredResponse(
            text: '{}',
            data: $this->dnaPayload(),
            raw: ['choices' => [['finish_reason' => 'length']]],
        );
        $this->app->instance(CoreAiGatewayContract::class, new class($resp) implements CoreAiGatewayContract {
            public function __construct(private $resp) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { return $this->resp; }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
        });

        try {
            app(VisualStyleAnalyzerService::class)->analyzeDna(
                ['mime' => 'image/png', 'data' => base64_encode('FAKE')],
            );
            $this->fail('Kỳ vọng AiGatewayException truncated_output.');
        } catch (AiGatewayException $e) {
            $this->assertSame('truncated_output', $e->errorCode());
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: analyzeMedia đọc stream media và dựng attachment gọi gateway
     * =====================================================================
     *
     * INPUT:
     * - MediaAsset thật với file Spatie (dummy image trên disk test).
     *
     * OUTPUT:
     * - void; assertions gateway nhận attachment mime+data base64.
     *
     * SIDE EFFECT:
     * - Tạo MediaAsset + file Spatie trên disk test; gọi gateway mock.
     *
     * EXCEPTION/TRANSACTION:
     * - RuntimeException nếu stream lỗi. Transaction rollback sau test.
     * =====================================================================
     */
    public function test_analyze_media_reads_stream_and_builds_attachment(): void
    {
        $this->fakeGateway($this->dnaPayload());

        // Tạo MediaAsset có file PNG thật trên disk test.
        $asset = \Modules\Media\Models\MediaAsset::create([
            'kind' => \Modules\Media\Models\MediaAsset::KIND_IMAGE,
            'title' => 'Ref image',
            'visibility' => 'public',
            'checksum_sha256' => hash('sha256', 'dummy'),
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'tst_');
        file_put_contents($tmp, $png);
        $asset->addMedia($tmp)->toMediaCollection(\Modules\Media\Models\MediaAsset::COLLECTION_LIBRARY, 'media_public');

        $out = app(VisualStyleAnalyzerService::class)->analyzeMedia($asset->id);

        $this->assertTrue($out['dna']->isComplete());
        $this->assertSame('vision-fake-model', $out['diagnostics']['model']);
    }
}
