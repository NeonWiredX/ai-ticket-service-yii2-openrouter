<?php

namespace app\services\Classifiers\OpenRouter;

/**
 * Настройки интеграции OpenRouter (иммутабельный value-object).
 * Транспортные поля (apiKey/baseUrl/referer/title/timeout) использует {@see OpenRouterClient},
 * параметры запроса (model/temperature) — {@see OpenRouterClassifier}.
 */
final class OpenRouterConfig
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $model,
        public readonly float $temperature = 0.0,
        public readonly string $baseUrl = 'https://openrouter.ai/api/v1',
        public readonly string $referer = '',
        public readonly string $title = '',
        public readonly float $timeout = 30.0,
    ) {
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function fromParams(array $params): self
    {
        return new self(
            apiKey: (string) ($params['apiKey'] ?? ''),
            model: (string) ($params['model'] ?? 'anthropic/claude-sonnet-4.5'),
            temperature: (float) ($params['temperature'] ?? 0.0),
            baseUrl: (string) ($params['baseUrl'] ?? 'https://openrouter.ai/api/v1'),
            referer: (string) ($params['referer'] ?? ''),
            title: (string) ($params['title'] ?? ''),
            timeout: (float) ($params['timeout'] ?? 30.0),
        );
    }
}
