<?php

namespace tests\unit\services;

use app\services\Classifiers\FakeClassifier;
use app\services\Dto\IngestTicketCommand;
use app\services\Policy\PolicyV1Service;
use app\services\Schema\ClassificationSchemaV1;
use app\services\TicketClassificationService;
use app\services\TicketIngestionService;
use app\services\TicketProcessingService;

/**
 * Интеграционный тест оркестрации (нужна БД): приём → классификация → сохранение,
 * плюс идемпотентность сабмита.
 */
class TicketProcessingServiceTest extends \Codeception\Test\Unit
{
    private function service(): TicketProcessingService
    {
        return new TicketProcessingService(
            new TicketIngestionService(),
            new TicketClassificationService(new FakeClassifier(), new PolicyV1Service(), new ClassificationSchemaV1()),
        );
    }

    private function command(?string $externalId = null): IngestTicketCommand
    {
        return new IngestTicketCommand(
            externalId: $externalId ?? 'EXT-' . uniqid(),
            tenantId: 'acme',
            userId: 'u1',
            subject: 'subj',
            body: 'body',
            source: 'email',
        );
    }

    public function testProcessClassifiesAndPersistsNewTicket(): void
    {
        $result = $this->service()->process($this->command());

        $this->assertFalse($result->skipped);
        $this->assertNotNull($result->decision->id);
        $this->assertSame($result->ticket->id, $result->decision->ticket_id);
        $this->assertSame('completed', $result->decision->status);
    }

    public function testProcessIsIdempotentForDuplicateSubmission(): void
    {
        $service = $this->service();
        $command = $this->command();

        $first = $service->process($command);
        $second = $service->process($command);

        $this->assertFalse($first->skipped);
        $this->assertTrue($second->skipped, 'повтор заново не классифицируется');
        $this->assertSame($first->ticket->id, $second->ticket->id);
        $this->assertSame($first->decision->id, $second->decision->id, 'возвращается то же решение');
    }
}
