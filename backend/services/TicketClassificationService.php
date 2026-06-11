<?php

namespace app\services;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\models\Enum\ClassificationStatus;
use app\models\Enum\PolicyDecision;
use app\models\Enum\RoutingDecision;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Exceptions\AiDecisionSaveException;
use app\services\Exceptions\ClassifierException;
use app\services\Exceptions\TicketSaveException;
use app\services\Exceptions\TicketValidationException;
use app\services\Policy\TicketPolicyInterface;

class TicketClassificationService
{
    public function __construct(
        protected TicketClassifierInterface $classifier,
        protected TicketPolicyInterface     $policy,
    )
    {
    }

    public function classify(array $model): AiDecision
    {
        $ticket = new Ticket();
        if (!$ticket->load($model, '') || !$ticket->validate()) {
            throw new TicketValidationException(
                json_encode($ticket->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }
        if (!$ticket->save(false)) {
            throw new TicketSaveException();
        }

        $classificationResult = $this->classifier->classify($ticket);
        if ($classificationResult->validationErrors){
            throw new ClassifierException(
                json_encode($classificationResult->validationErrors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }
        $policyDecision = $this->policy->checkPolicy($classificationResult);

        $aiDecision = new AiDecision(['ticket_id' => $ticket->id]);
        $aiDecision->load($classificationResult->toAiDecisionAttributes(), '');
        // status DTO не несёт — выводим из наличия ошибок разбора.
        $aiDecision->status = ($classificationResult->validationErrors === null
            ? ClassificationStatus::COMPLETED
            : ClassificationStatus::FAILED)->value;
        $aiDecision->policy_version = $this->policy->getVersion();
        $aiDecision->policy_decision = $policyDecision->value;

        $aiDecision->final_routing_decision = $this->resolveRoute($policyDecision, $classificationResult->modelRoutingDecision)->value;
        $aiDecision->executable_actions_allowed = $policyDecision === PolicyDecision::ALLOWED;

        if (!$aiDecision->save()) {
            throw new AiDecisionSaveException(
                json_encode($aiDecision->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }

        return $aiDecision;
    }

    /**
     * Итоговый маршрут — всегда RoutingDecision (не вердикт политики):
     * ALLOWED → маршрут модели (фолбэк MANUAL_TRIAGE, если пусто),
     * REQUIRES_APPROVAL → HUMAN_REVIEW, BLOCKED → MANUAL_TRIAGE.
     */
    private function resolveRoute(PolicyDecision $decision, ?RoutingDecision $modelRoute): RoutingDecision
    {
        return match ($decision) {
            PolicyDecision::ALLOWED => $modelRoute ?? RoutingDecision::MANUAL_TRIAGE,
            PolicyDecision::REQUIRES_APPROVAL => RoutingDecision::HUMAN_REVIEW,
            PolicyDecision::BLOCKED => RoutingDecision::MANUAL_TRIAGE,
        };
    }
}
