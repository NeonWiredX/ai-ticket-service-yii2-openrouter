<?php

namespace tests\unit\services;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\services\Classifiers\FakeClassifier;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Dto\ClassificationResultDto;
use app\services\Dto\IngestTicketCommand;
use app\services\Policy\PolicyV1Service;
use app\services\Schema\ClassificationSchemaInterface;
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
    private function service(?TicketClassifierInterface $classifier = null): TicketProcessingService
    {
        return new TicketProcessingService(
            new TicketIngestionService(),
            new TicketClassificationService(
                $classifier ?? new FakeClassifier(),
                new PolicyV1Service(),
                new ClassificationSchemaV1(),
            ),
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

        $this->assertFalse($result->classificationSkipped);
        $this->assertNotNull($result->decision->id);
        $this->assertSame($result->ticket->id, $result->decision->ticket_id);
        $this->assertSame('completed', $result->decision->status);
    }

    public function testProcessIsIdempotentForDuplicateSubmission(): void
    {
        // спай поверх FakeClassifier — считает вызовы классификатора
        $classifier = new class(new FakeClassifier()) implements TicketClassifierInterface {
            public int $calls = 0;

            public function __construct(private TicketClassifierInterface $inner)
            {
            }

            public function classify(Ticket $ticket, ClassificationSchemaInterface $schema): ClassificationResultDto
            {
                $this->calls++;

                return $this->inner->classify($ticket, $schema);
            }
        };

        $service = $this->service($classifier);
        $command = $this->command();

        $first = $service->process($command);
        $second = $service->process($command);

        // повтор не классифицирует заново и не плодит строк
        $this->assertSame(1, $classifier->calls, 'классификатор вызван ровно один раз');
        $this->assertFalse($first->classificationSkipped);
        $this->assertTrue($second->classificationSkipped, 'повтор заново не классифицируется');
        $this->assertSame($first->decision->id, $second->decision->id, 'возвращается то же решение');
        $this->assertEquals(1, Ticket::find()->where([
            'tenant_id' => $command->tenantId,
            'external_id' => $command->externalId,
        ])->count(), 'ровно один тикет');
        $this->assertEquals(
            1,
            AiDecision::find()->where(['ticket_id' => $first->ticket->id])->count(),
            'ровно одно решение',
        );
    }
}
