<?php

namespace tests\integration;

use app\models\Entity\Ticket;
use app\services\Dto\IngestTicketCommand;
use app\services\Exceptions\TicketValidationException;
use app\services\TicketIngestionService;

/**
 * Интеграционный тест приёма (нужна БД): дедуп держится на DB-констрейнте через
 * INSERT ... ON CONFLICT, поэтому проверяем реальное поведение, а не мок.
 */
class TicketIngestionServiceTest extends \Codeception\Test\Unit
{
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

    public function testCreatesNewTicket(): void
    {
        $result = (new TicketIngestionService())->ingest($this->command());

        $this->assertTrue($result->wasCreated);
        $this->assertNotNull($result->ticket->id);
        $this->assertSame('acme', $result->ticket->tenant_id);
    }

    public function testDuplicateReturnsExistingAndDoesNotCreate(): void
    {
        $service = new TicketIngestionService();
        $command = $this->command();

        $first = $service->ingest($command);
        $second = $service->ingest($command); // тот же (tenant_id, external_id)

        $this->assertTrue($first->wasCreated);
        $this->assertFalse($second->wasCreated, 'дубль не создаётся');
        $this->assertSame($first->ticket->id, $second->ticket->id, 'возвращается тот же тикет');
        $this->assertEquals(1, Ticket::find()->where([
            'tenant_id' => $command->tenantId,
            'external_id' => $command->externalId,
        ])->count(), 'в БД ровно одна строка');
    }

    public function testInvalidInputThrowsValidation(): void
    {
        $this->expectException(TicketValidationException::class);

        (new TicketIngestionService())->ingest($this->command(externalId: '')); // пустой external_id
    }
}
