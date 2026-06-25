<?php

namespace app\services\Classifiers\OpenRouter;

use app\models\Entity\Ticket;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Dto\ClassificationResultDto;
use app\services\Prompt\TicketPromptInterface;
use app\services\Schema\ClassificationSchemaInterface;

/**
 * Классификатор через OpenRouter: собирает запрос из промпта и схемы, зовёт {@see OpenRouterClient},
 * маппит ответ модели в DTO. Транспортные сбои пробрасывает как ClassifierException (их кидает клиент);
 * невалидный ответ модели сбоем не считается — уходит в validationErrors через schema->parse().
 */
final class OpenRouterClassifier implements TicketClassifierInterface
{
    public function __construct(
        private OpenRouterClient $client,
        private OpenRouterConfig $config,
    ) {
    }

    public function classify(Ticket $ticket, ClassificationSchemaInterface $schema, TicketPromptInterface $prompt): ClassificationResultDto
    {
        $payload = [
            'model' => $this->config->model,
            'temperature' => $this->config->temperature,
            'messages' => [
                ['role' => 'system', 'content' => $prompt->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt->renderUserPrompt($ticket)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'ticket_classification',
                    'strict' => true,
                    'schema' => $schema->getSchema(),
                ],
            ],
            'provider' => ['require_parameters' => true],
        ];

        $startedAt = microtime(true);
        $response = $this->client->chatCompletion($payload); // ClassifierException пробрасывается
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $content = $response['choices'][0]['message']['content'] ?? '';
        $rawOutput = is_string($content) ? json_decode($content, true) : null;
        if (!is_array($rawOutput)) {
            $rawOutput = []; // мусор/не-JSON → validationErrors, не исключение
        }

        return $schema->parse(
            $rawOutput,
            model: is_string($response['model'] ?? null) ? $response['model'] : $this->config->model,
            promptVersion: $prompt->getVersion(),
            traceId: is_string($response['id'] ?? null) ? $response['id'] : 'trace-' . bin2hex(random_bytes(8)),
            latencyMs: $latencyMs,
            retryCount: 0,
        );
    }
}
