<?php

namespace app\services\Classifiers\OpenRouter;

use app\services\Exceptions\ClassifierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Транспорт OpenRouter: знает endpoint, auth и заголовки; шлёт chat completions и отдаёт
 * декодированный ответ. Любой сбой ВЫЗОВА (сеть/таймаут/HTTP не-2xx/не-JSON конверт) — это
 * {@see ClassifierException}. Семантику самого ответа модели разбирает уже классификатор.
 */
final class OpenRouterClient
{
    public function __construct(
        private HttpClientInterface $http,
        private OpenRouterConfig $config,
    ) {
    }

    /**
     * @param array<string,mixed> $payload тело запроса (model/messages/response_format/provider)
     * @return array<string,mixed> декодированный JSON-ответ OpenRouter
     * @throws ClassifierException при сбое вызова
     */
    public function chatCompletion(array $payload): array
    {
        $url = rtrim($this->config->baseUrl, '/') . '/chat/completions';

        try {
            $response = $this->http->request('POST', $url, [
                'headers' => $this->headers(),
                'json' => $payload,
                'timeout' => $this->config->timeout,
            ]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false); // false — не кидать на 4xx/5xx, обрабатываем сами
        } catch (ExceptionInterface $e) {
            throw new ClassifierException('OpenRouter transport error: ' . $e->getMessage(), 0, $e);
        }

        if ($status < 200 || $status >= 300) {
            throw new ClassifierException("OpenRouter HTTP {$status}: {$body}");
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ClassifierException('OpenRouter: ответ не является JSON-объектом.');
        }

        return $decoded;
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($this->config->referer !== '') {
            $headers['HTTP-Referer'] = $this->config->referer;
        }
        if ($this->config->title !== '') {
            $headers['X-Title'] = $this->config->title;
        }

        return $headers;
    }
}
