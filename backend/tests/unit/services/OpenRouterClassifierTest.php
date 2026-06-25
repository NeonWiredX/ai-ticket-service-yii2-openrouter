<?php

namespace tests\unit\services;

use app\models\Entity\Ticket;
use app\models\Enum\Category;
use app\services\Classifiers\OpenRouter\OpenRouterClassifier;
use app\services\Classifiers\OpenRouter\OpenRouterClient;
use app\services\Classifiers\OpenRouter\OpenRouterConfig;
use app\services\Dto\ClassificationResultDto;
use app\services\Prompt\ClassificationPromptV1;
use app\services\Schema\ClassificationSchemaV1;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Классификатор тестируется поверх реального OpenRouterClient с MockHttpClient — без сети и БД.
 */
class OpenRouterClassifierTest extends \Codeception\Test\Unit
{
    /** In-memory тикет без обращения к схеме БД (переопределён attributes()). */
    private function ticket(): Ticket
    {
        $ticket = new class extends Ticket {
            public function attributes(): array
            {
                return ['id', 'external_id', 'tenant_id', 'user_id', 'subject', 'body', 'source', 'created_at'];
            }
        };
        $ticket->subject = 'нет счёта';
        $ticket->body = 'третий день не приходит инвойс';

        return $ticket;
    }

    private function classify(MockHttpClient $http): ClassificationResultDto
    {
        $config = new OpenRouterConfig(apiKey: 'test-key', model: 'anthropic/claude-sonnet-4.5');
        $classifier = new OpenRouterClassifier(new OpenRouterClient($http, $config), $config);

        return $classifier->classify($this->ticket(), new ClassificationSchemaV1(), new ClassificationPromptV1());
    }

    private function envelope(string $content, string $id = 'gen-1', string $model = 'anthropic/claude-sonnet-4.5'): string
    {
        return json_encode([
            'id' => $id,
            'model' => $model,
            'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
        ]);
    }

    public function testBuildsRequestAndMapsResponseToDto(): void
    {
        $content = json_encode([
            'category' => 'billing', 'priority' => 'low', 'risk' => 'none',
            'routing_decision' => 'support_queue', 'confidence' => 0.9, 'summary' => 's', 'reason' => 'r',
        ]);
        $captured = null;
        $http = new MockHttpClient(function ($method, $url, $options) use (&$captured, $content) {
            $captured = compact('url', 'options');

            return new MockResponse($this->envelope($content), ['http_code' => 200]);
        });

        $dto = $this->classify($http);

        // DTO собран из ответа модели
        $this->assertNull($dto->validationErrors);
        $this->assertSame(Category::BILLING, $dto->category);
        $this->assertSame('anthropic/claude-sonnet-4.5', $dto->model);
        $this->assertSame('gen-1', $dto->traceId);
        $this->assertSame('prompt.v1', $dto->promptVersion);
        $this->assertSame('classification.v1', $dto->schemaVersion);

        // запрос построен из промпта и схемы
        $this->assertStringEndsWith('/chat/completions', $captured['url']);
        $body = json_decode($captured['options']['body'], true);
        $this->assertSame('anthropic/claude-sonnet-4.5', $body['model']);
        $this->assertSame('system', $body['messages'][0]['role']);
        $this->assertStringContainsString('нет счёта', $body['messages'][1]['content']);
        $this->assertTrue($body['response_format']['json_schema']['strict']);
        $this->assertTrue($body['provider']['require_parameters']);
    }

    public function testMalformedContentYieldsValidationErrors(): void
    {
        // модель вернула не-JSON в content → не исключение, а validationErrors
        $http = new MockHttpClient(new MockResponse($this->envelope('не json'), ['http_code' => 200]));

        $dto = $this->classify($http);

        $this->assertNotNull($dto->validationErrors);
        $this->assertNull($dto->category);
    }
}
