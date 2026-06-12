<?php

namespace app\services;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\models\Enum\ClassificationStatus;
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
        if ($classificationResult->validationErrors) {
            throw new ClassifierException(
                json_encode($classificationResult->validationErrors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }

        $policyResult = $this->policy->checkPolicy($classificationResult);

        $aiDecision = new AiDecision(['ticket_id' => $ticket->id]);
        $aiDecision->load($classificationResult->toAiDecisionAttributes(), '');
        $aiDecision->load($policyResult->toAiDecisionAttributes(), '');
        $aiDecision->status = ($classificationResult->validationErrors === null
            ? ClassificationStatus::COMPLETED
            : ClassificationStatus::FAILED)->value;

        if (!$aiDecision->save()) {
            throw new AiDecisionSaveException(
                json_encode($aiDecision->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }

        return $aiDecision;
    }
}
