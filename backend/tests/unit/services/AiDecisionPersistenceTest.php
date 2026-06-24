<?php

namespace tests\unit\services;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\models\Enum\Category;
use app\models\Enum\ClassificationStatus;
use app\models\Enum\PolicyDecision;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\AiDecisionDto;
use app\services\Dto\ClassificationResultDto;
use app\services\Dto\PolicyResultDto;
use app\services\Policy\PolicyV1Service;

/**
 * Граница «DTO → AiDecision» (слой персистентности, пока «тупой» — как в TestTicketController).
 * Проверяет, что AiDecisionDto::toAiDecisionAttributes() целиком ложится на safe-атрибуты
 * AiDecision и сохраняется со связью к тикету — страховка от рассинхрона ключей DTO и rules().
 */
class AiDecisionPersistenceTest extends \Codeception\Test\Unit
{
    private function persistedTicket(): Ticket
    {
        $ticket = new Ticket([
            'external_id' => 'EXT-TEST-' . uniqid(),
            'tenant_id' => 'acme',
            'user_id' => 'u1',
            'subject' => 'subj',
            'body' => 'body',
            'source' => 'email',
        ]);
        if (!$ticket->save()) {
            throw new \RuntimeException(
                'тестовый тикет не сохранился: ' . json_encode($ticket->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $ticket;
    }

    private function decisionFor(Ticket $ticket): AiDecisionDto
    {
        return new AiDecisionDto(
            ticketId: $ticket->id,
            status: ClassificationStatus::COMPLETED,
            classification: new ClassificationResultDto(
                category: Category::BILLING,
                priority: Priority::LOW,
                risk: Risk::NONE,
                confidence: 0.9,
                summary: 'sum',
                reason: 'rsn',
                modelRoutingDecision: RoutingDecision::SUPPORT_QUEUE,
                model: 'stub',
                schemaVersion: 'classification.v1',
                traceId: 'trace-x',
            ),
            policy: new PolicyResultDto(
                decision: PolicyDecision::ALLOWED,
                finalRoutingDecision: RoutingDecision::SUPPORT_QUEUE,
                matchedRules: ['auto_allowed'],
                policyVersion: PolicyV1Service::VERSION,
            ),
        );
    }

    public function testDecisionDtoMapsAndPersistsLinkedToTicket(): void
    {
        $ticket = $this->persistedTicket();
        $decision = $this->decisionFor($ticket);

        // «тупая» персистентность — ровно как делает TestTicketController
        $ai = new AiDecision();
        $ai->load($decision->toAiDecisionAttributes(), '');
        $this->assertTrue($ai->save(), json_encode($ai->getErrors(), JSON_UNESCAPED_UNICODE));

        $reloaded = AiDecision::findOne($ai->id);
        $this->assertNotNull($reloaded);
        $this->assertSame($ticket->id, $reloaded->ticket_id);
        $this->assertSame('completed', $reloaded->status);
        $this->assertSame('billing', $reloaded->category);
        $this->assertSame('allowed', $reloaded->policy_decision);
        $this->assertTrue((bool) $reloaded->executable_actions_allowed);
        // нетривиальные поля долетели до записи (страховка D1)
        $this->assertSame('stub', $reloaded->model);
        $this->assertSame('classification.v1', $reloaded->schema_version);
        $this->assertSame(PolicyV1Service::VERSION, $reloaded->policy_version);
        $this->assertSame('trace-x', $reloaded->trace_id);
        $this->assertSame(['auto_allowed'], $reloaded->matched_rules);
        $this->assertInstanceOf(Ticket::class, $reloaded->ticket);
    }
}
