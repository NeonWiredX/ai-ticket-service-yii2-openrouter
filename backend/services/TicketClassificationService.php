<?php

namespace app\services;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\services\Classifiers\TicketClassifierInterface;
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
        if (!$ticket->load($model,'') || !$ticket->validate()) {
            throw new TicketValidationException();
        }
        if (!$ticket->save()) {
            throw new TicketSaveException();
        }

        $classificationResult = $this->classifier->classify($ticket);

        $policyDecision = $this->policy->checkPolicy($classificationResult);

        $aiDecision = new AiDecision();
        $aiDecision->load([


            'policy_version' => $this->policy->getVersion(),
            'policy_decision' => $policyDecision->value,
        ]);
        $aiDecision->save();

        return $aiDecision;
    }
}