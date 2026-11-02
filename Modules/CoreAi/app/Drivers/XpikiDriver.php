<?php

namespace Modules\CoreAi\Drivers;

use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;

/** XpikiDriver: api.xpiki.com async — POST /images/generations {model,prompt}
 * -> 202 task_id -> GET /generation-tasks/{id} poll -> GET outputs[i].url. */
class XpikiDriver extends AbstractAiDriver
{
    public function key(): string { return 'xpiki'; }
    protected function ops(): array { return ['image']; }
    protected function headers(string $apiKey): array
    { return $this->client()->buildAuthHeaders('Authorization', $apiKey, 'Bearer'); }

    public function test(AiResolvedModelDto $resolved): array
    {
        $start = microtime(true);
        try {
            $res = $this->client()->request($resolved->provider, 'GET',
                $this->url($resolved->provider, 'models?type=all'),
                $this->headers($resolved->apiKey), null, ['timeout' => 15]);
            $data = json_decode($res->body, true);
            $ids = is_array($data['data'] ?? null)
                ? array_values(array_filter(array_column($data['data'], 'id'))) : [];
            $ms = (int) round((microtime(true) - $start) * 1000);
            return ['ok'=>true,'latency_ms'=>$ms,
                'message'=>'Kết nối thành công — '.count($ids).' models',
                'models'=>$ids];
        } catch (AiGatewayException $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);
            return ['ok'=>false,'latency_ms'=>$ms,
                'message'=>$e->getMessage(),'error_code'=>$e->errorCode()];
        }
    }

    public function image(AiResolvedModelDto $resolved, AiImageRequest $request): AiImageResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            $payload = ['model'=>$r->model->remote_model_id, 'prompt'=>$request->prompt];
            if ($request->aspectRatio !== '' && $request->aspectRatio !== '1:1')
                $payload['aspect_ratio'] = $request->aspectRatio;
            $task = $this->submitTask($r, 'images/generations', $payload);
            $done = $this->waitTask($r, $task);
            $images = $this->downloadOutputs($r, $done, 'image/');
            if ($images === [])
                throw AiGatewayException::providerError('xpiki task xong nhung ko co anh', ['provider'=>$r->provider->key]);
            return new AiImageResponse(images:$images, usage:['credit'=>$done['final_credit']??null],
                raw:$done, remoteModelId:$r->model->remote_model_id, providerKey:$r->provider->key);
        });
    }

    protected function submitTask(AiResolvedModelDto $r, string $path, array $payload): array
    {
        $task = $this->client()->requestJson($r->provider, $this->url($r->provider, $path),
            $payload, $this->headers($r->apiKey), ['keyIndex'=>$r->keyIndex,'timeout'=>60]);
        if (! is_string($task['task_id'] ?? null))
            throw AiGatewayException::providerError('xpiki khong tra task_id', ['response'=>$task]);
        return $task;
    }

    protected function waitTask(AiResolvedModelDto $r, array $task): array
    {
        $id = $task['task_id'];
        $statusUrl = $this->url($r->provider, 'generation-tasks/' . $id);
        $timeout = (int)($r->provider->request_timeout ?? 180);
        $deadline = microtime(true) + min($timeout, 300);
        while (microtime(true) < $deadline) {
            $res = $this->client()->request($r->provider, 'GET', $statusUrl,
                $this->headers($r->apiKey), null, ['keyIndex'=>$r->keyIndex,'timeout'=>30]);
            $cur = json_decode($res->body, true) ?: [];
            $status = $cur['status'] ?? 'unknown';
            if ($status === 'succeeded') return $cur;
            if (in_array($status, ['failed','error','cancelled','canceled'], true))
                throw AiGatewayException::providerError(
                    'xpiki task that bai: '.($cur['error']??$cur['message']??$status), ['task'=>$id]);
            usleep(1000000);
        }
        throw AiGatewayException::providerError('xpiki task qua thoi gian cho', ['task'=>$id]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tải từng output binary trong task về (ảnh/audio...).
     * =====================================================================
     * `outputs[].url` có thể là relative ('outputs/1.png') HOẶC tuyệt đối:
     * - Cùng host base_url (api.xpiki.com)   -> dùng trực tiếp + gửi Bearer
     *   (vẫn là endpoint của chính provider, cần auth).
     * - Host khác (CDN: storage.xpiki.com / S3/R2 presigned) -> dùng trực
     *   tiếp, KHÔNG gửi Authorization: presigned đã ký sẵn trong query,
     *   gửi thêm Bearer bị server từ chối "Only one auth mechanism" và
     *   còn rò rỉ API key ra host ngoài. SSRF vẫn được assertUrlAllowed
     *   chặn (private IP/localhost) nên cho phép host ngoài là an toàn.
     *
     * INPUT: resolved + task đã succeeded + $mimePrefix lọc loại file.
     * OUTPUT: array{mime,data,width,height}[] — binary đã tải.
     * =====================================================================
     */
    protected function downloadOutputs(AiResolvedModelDto $r, array $task, string $mimePrefix): array
    {
        $images = [];
        $baseHost = strtolower((string) parse_url($r->provider->base_url, PHP_URL_HOST));

        foreach ((array)($task['outputs'] ?? []) as $out) {
            $rel = $out['url'] ?? null; $mime = (string)($out['mime_type'] ?? '');
            if (! is_string($rel) || $rel === '') continue;
            if ($mimePrefix !== '' && $mime !== '' && ! str_starts_with($mime, $mimePrefix)) continue;

            if (preg_match('/^https?:\/\//i', $rel)) {
                // URL tuyệt đối: chỉ giữ Bearer khi cùng host với provider.
                $outHost = strtolower((string) parse_url($rel, PHP_URL_HOST));
                $headers = ($outHost !== '' && $outHost === $baseHost)
                    ? $this->headers($r->apiKey)
                    : [];
                $bin = $this->client()->request($r->provider, 'GET', $rel,
                    $headers, null, ['keyIndex'=>$r->keyIndex,'maxBytes'=>64*1024*1024,'timeout'=>120]);
            } else {
                $bin = $this->client()->request($r->provider, 'GET', $this->url($r->provider, $rel),
                    $this->headers($r->apiKey), null, ['keyIndex'=>$r->keyIndex,'maxBytes'=>64*1024*1024,'timeout'=>120]);
            }

            $images[] = ['mime'=>$mime!==''?$mime:($bin->contentType??'image/png'),
                'data'=>$bin->body, 'width'=>null, 'height'=>null];
        }
        return $images;
    }

    public function structured(AiResolvedModelDto $r, AiStructuredRequest $q): AiStructuredResponse { $this->unsupported('structured'); }
    public function speech(AiResolvedModelDto $r, AiSpeechRequest $q): AiSpeechResponse { $this->unsupported('speech'); }
    public function transcribe(AiResolvedModelDto $r, AiTranscribeRequest $q): AiTranscribeResponse { $this->unsupported('transcribe'); }
}
