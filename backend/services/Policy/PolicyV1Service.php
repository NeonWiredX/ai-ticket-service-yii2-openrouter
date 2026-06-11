<?php

namespace app\services\Policy;

use app\models\Enum\PolicyDecision;
use app\models\Enum\Risk;
use app\services\Dto\ClassificationResultDto;

/**
 * Политика v1: решает, можно ли выполнять действия по тикету автоматически.
 */
class PolicyV1Service implements TicketPolicyInterface
{
    private const APPROVAL_THRESHOLD = 0.6;

    private const RISKY = [
        Risk::DESTRUCTIVE_ACTION,
        Risk::MONEY_MOVEMENT,
        Risk::EXTERNAL_SEND,
        Risk::SECURITY,
        Risk::PRIVACY,
    ];

    public function getVersion(): string
    {
        return 'v1';
    }

    public function checkPolicy(ClassificationResultDto $classificationResultDto): PolicyDecision
    {
        // Провал разбора ответа модели — ничего автоматически не выполняем.
        if ($classificationResultDto->validationErrors !== null) {
            return PolicyDecision::BLOCKED;
        }

        // Рискованные категории — только через ручное одобрение.
        if (in_array($classificationResultDto->risk, self::RISKY, true)) {
            return PolicyDecision::REQUIRES_APPROVAL;
        }

        // Низкая уверенность модели — тоже на одобрение.
        if ($classificationResultDto->confidence !== null
            && $classificationResultDto->confidence < self::APPROVAL_THRESHOLD
        ) {
            return PolicyDecision::REQUIRES_APPROVAL;
        }

        return PolicyDecision::ALLOWED;
    }
}
