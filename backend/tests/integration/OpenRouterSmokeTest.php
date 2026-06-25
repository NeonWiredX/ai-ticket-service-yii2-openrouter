<?php

namespace tests\integration;

use app\models\Entity\Ticket;
use app\services\Classifiers\OpenRouter\OpenRouterClassifier;
use app\services\Classifiers\OpenRouter\OpenRouterClient;
use app\services\Classifiers\OpenRouter\OpenRouterConfig;
use app\services\Prompt\ClassificationPromptV1;
use app\services\Schema\ClassificationSchemaV1;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Смок: реальный вызов OpenRouter (сеть!). Запускается только если задан OPENROUTER_API_KEY,
 * иначе пропускается. БД не нужна (in-memory тикет) — сьют integration без orm.
 *
 * Прогон с ключом:
 *   docker compose run --rm --no-deps -e OPENROUTER_API_KEY=sk-... php \
 *     vendor/bin/codecept run integration
 */
class OpenRouterSmokeTest extends \Codeception\Test\Unit
{
    public function testClassifiesRealTicketViaOpenRouter(): void
    {
        $apiKey = getenv('OPENROUTER_API_KEY') ?: '';
        if ($apiKey === '') {
            $this->markTestSkipped('OPENROUTER_API_KEY не задан — смок пропущен.');
        }

        $config = new OpenRouterConfig(
            apiKey: $apiKey,
            model: getenv('OPENROUTER_MODEL') ?: 'anthropic/claude-sonnet-4.5',
        );
        $classifier = new OpenRouterClassifier(new OpenRouterClient(HttpClient::create(), $config), $config);

        $dto = $classifier->classify($this->ticket(), new ClassificationSchemaV1(), new ClassificationPromptV1());

        // ключевая проверка: весь пайплайн (запрос → structured output → разбор) дал валидную классификацию
        $this->assertNull(
            $dto->validationErrors,
            'ожидали валидный structured output, получили: ' . json_encode($dto->validationErrors, JSON_UNESCAPED_UNICODE)
        );
        $this->assertNotNull($dto->category);
        $this->assertNotNull($dto->confidence);
        $this->assertSame('prompt.v1', $dto->promptVersion);
        $this->assertNotSame('', $dto->traceId);
    }

    /** In-memory тикет без обращения к схеме БД (переопределён attributes()). */
    private function ticket(): Ticket
    {
        $ticket = new class extends Ticket {
            public function attributes(): array
            {
                return ['id', 'external_id', 'tenant_id', 'user_id', 'subject', 'body', 'source', 'created_at'];
            }
        };
        $ticket->subject = 'Двойное списание с карты при оплате подписки';
        $ticket->body = 'Сегодня дважды списали за месячную подписку. Прошу вернуть одно из списаний.';

        return $ticket;
    }
}
