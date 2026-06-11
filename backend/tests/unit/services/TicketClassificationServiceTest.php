<?php

namespace tests\unit\services;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Dto\ClassificationResultDto;
use app\services\Exceptions\ClassifierException;
use app\services\Exceptions\TicketValidationException;
use app\services\Policy\PolicyV1Service;
use app\services\TicketClassificationService;

class TicketClassificationServiceTest extends \Codeception\Test\Unit
{
    private function service(?TicketClassifierInterface $classifier = null): TicketClassificationService
    {
        return new TicketClassificationService(
            $classifier ?? $this->stubClassifier(['risk' => 'none', 'confidence' => 0.95]),
            new PolicyV1Service(),
        );
    }

    /**
     * Классификатор с фиксированным ответом — для детерминированных проверок.
     *
     * @param array<string,mixed> $output
     */
    private function stubClassifier(array $output): TicketClassifierInterface
    {
        return new class($output) implements TicketClassifierInterface {
            /** @param array<string,mixed> $output */
            public function __construct(private array $output)
            {
            }

            public function classify(Ticket $ticket): ClassificationResultDto
            {
                return ClassificationResultDto::fromModelOutput(
                    $this->output + ['routing_decision' => 'support_queue', 'category' => 'billing'],
                    model: 'stub',
                    schemaVersion: 'v1',
                    traceId: 'trace-x',
                );
            }
        };
    }

    /** @return array<string,string> валидный вход для тикета */
    private function ticketInput(): array
    {
        return [
            'external_id' => 'EXT-TEST-' . uniqid(),
            'tenant_id' => 'acme',
            'user_id' => 'u1',
            'subject' => 'subj',
            'body' => 'body',
            'source' => 'email',
        ];
    }

    public function testClassifyPersistsTicketAndLinkedDecision(): void
    {
        $ai = $this->service()->classify($this->ticketInput());

        $this->assertNotNull($ai->id);
        $this->assertNotNull($ai->ticket_id);
        $this->assertSame('completed', $ai->status);
        $this->assertSame('billing', $ai->category);
        $this->assertNotNull(AiDecision::findOne($ai->id), 'решение должно сохраниться');
        // связь в обе стороны
        $this->assertSame($ai->ticket_id, $ai->ticket->id);
        $this->assertInstanceOf(Ticket::class, $ai->ticket);
    }

    public function testAllowedDecisionEnablesActionsAndKeepsModelRoute(): void
    {
        // risk=none + confidence 0.95 → ALLOWED
        $ai = $this->service()->classify($this->ticketInput());

        $this->assertSame('allowed', $ai->policy_decision);
        $this->assertTrue((bool) $ai->executable_actions_allowed);
        $this->assertSame('support_queue', $ai->final_routing_decision);
        $this->assertSame('v1', $ai->policy_version);
    }

    public function testRiskyDecisionRequiresApproval(): void
    {
        $service = $this->service($this->stubClassifier(['risk' => 'security', 'confidence' => 0.99]));

        $ai = $service->classify($this->ticketInput());

        $this->assertSame('requires_approval', $ai->policy_decision);
        $this->assertFalse((bool) $ai->executable_actions_allowed);
        // не ALLOWED → маршрут к человеку (RoutingDecision, не вердикт политики)
        $this->assertSame('human_review', $ai->final_routing_decision);
    }

    public function testAllowedWithoutModelRouteFallsBackToManualTriage(): void
    {
        // ALLOWED, но модель не вернула маршрут → фолбэк MANUAL_TRIAGE
        $service = $this->service($this->stubClassifier([
            'risk' => 'none',
            'confidence' => 0.95,
            'routing_decision' => null,
        ]));

        $ai = $service->classify($this->ticketInput());

        $this->assertSame('allowed', $ai->policy_decision);
        $this->assertNull($ai->model_routing_decision);
        $this->assertSame('manual_triage', $ai->final_routing_decision);
    }

    public function testInvalidTicketThrowsValidation(): void
    {
        $this->expectException(TicketValidationException::class);

        $this->service()->classify(['external_id' => '']); // не хватает обязательных полей
    }

    public function testClassifierErrorsAbortWithException(): void
    {
        // битый enum в ответе модели → ClassifierException ещё до сохранения решения
        $service = $this->service($this->stubClassifier(['category' => 'garbage']));

        $this->expectException(ClassifierException::class);
        $service->classify($this->ticketInput());
    }
}
