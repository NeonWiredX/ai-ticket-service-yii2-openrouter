<?php

namespace tests\unit\services;

use app\models\Entity\Ticket;
use app\models\Enum\Category;
use app\models\Enum\ClassificationStatus;
use app\models\Enum\PolicyDecision;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Dto\AiDecisionDto;
use app\services\Dto\ClassificationResultDto;
use app\services\Exceptions\ClassifierException;
use app\services\Policy\PolicyV1Service;
use app\services\Schema\ClassificationSchemaInterface;
use app\services\Schema\ClassificationSchemaV1;
use app\services\TicketClassificationService;

/**
 * Чистый юнит: сервис не трогает БД (возвращает DTO), поэтому тикет — in-memory.
 * Поведение политики покрыто PolicyV1ServiceTest; здесь — сборка решения и вывод статуса.
 */
class TicketClassificationServiceTest extends \Codeception\Test\Unit
{
    /**
     * @param array<string,string>|null $validationErrors
     */
    private function dto(
        ?Risk $risk = Risk::NONE,
        ?float $confidence = 0.95,
        ?RoutingDecision $route = RoutingDecision::SUPPORT_QUEUE,
        ?array $validationErrors = null,
    ): ClassificationResultDto {
        return new ClassificationResultDto(
            category: Category::BILLING,
            priority: Priority::LOW,
            risk: $risk,
            confidence: $confidence,
            summary: null,
            reason: null,
            modelRoutingDecision: $route,
            model: 'stub',
            schemaVersion: 'classification.v1',
            traceId: 'trace-x',
            validationErrors: $validationErrors,
        );
    }

    private function service(?ClassificationResultDto $dto = null): TicketClassificationService
    {
        return new TicketClassificationService(
            $this->stubClassifier($dto ?? $this->dto()),
            new PolicyV1Service(),
            new ClassificationSchemaV1(),
        );
    }

    /** Классификатор с фиксированным результатом — схему игнорирует (она не на проверке). */
    private function stubClassifier(ClassificationResultDto $dto): TicketClassifierInterface
    {
        return new class($dto) implements TicketClassifierInterface {
            public function __construct(private ClassificationResultDto $dto)
            {
            }

            public function classify(Ticket $ticket, ClassificationSchemaInterface $schema): ClassificationResultDto
            {
                return $this->dto;
            }
        };
    }

    /** In-memory тикет с проставленным id — сервис БД не трогает. */
    private function ticket(int $id = 1): Ticket
    {
        $ticket = new Ticket();
        $ticket->id = $id;

        return $ticket;
    }

    public function testAssemblesCompletedDecisionLinkedToTicket(): void
    {
        $decision = $this->service()->classify($this->ticket(7));

        $this->assertInstanceOf(AiDecisionDto::class, $decision);
        $this->assertSame(7, $decision->ticketId);
        $this->assertSame(ClassificationStatus::COMPLETED, $decision->status);
        $this->assertSame(Category::BILLING, $decision->classification->category);
        $this->assertSame(PolicyDecision::ALLOWED, $decision->policy->decision);
    }

    public function testValidationErrorsYieldFailedAndBlocked(): void
    {
        $decision = $this->service($this->dto(validationErrors: ['category' => 'bad']))
            ->classify($this->ticket());

        $this->assertSame(ClassificationStatus::FAILED, $decision->status);
        $this->assertSame(PolicyDecision::BLOCKED, $decision->policy->decision);
        $this->assertSame(RoutingDecision::MANUAL_TRIAGE, $decision->policy->finalRoutingDecision);
    }

    public function testToAttributesMergesTicketStatusClassificationAndPolicy(): void
    {
        $attrs = $this->service()->classify($this->ticket(42))->toAiDecisionAttributes();

        $this->assertSame(42, $attrs['ticket_id']);
        $this->assertSame('completed', $attrs['status']);
        $this->assertSame('billing', $attrs['category']);          // из classification
        $this->assertSame('allowed', $attrs['policy_decision']);   // из policy
        $this->assertTrue($attrs['executable_actions_allowed']);
    }

    public function testTransportFailurePropagates(): void
    {
        // сбой самого вызова модели (не валидация) — исключение пробрасывается наверх
        $service = new TicketClassificationService(
            new class implements TicketClassifierInterface {
                public function classify(Ticket $ticket, ClassificationSchemaInterface $schema): ClassificationResultDto
                {
                    throw new ClassifierException('model unreachable');
                }
            },
            new PolicyV1Service(),
            new ClassificationSchemaV1(),
        );

        $this->expectException(ClassifierException::class);
        $service->classify($this->ticket());
    }
}
