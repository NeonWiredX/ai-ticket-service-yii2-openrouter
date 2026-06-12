<?php

namespace app\services\Policy;

use app\models\Enum\PolicyDecision;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;
use app\services\Dto\PolicyResultDto;

/**
 * Политика v1: решает, можно ли выполнять действия по тикету автоматически,
 * и куда его направить.
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
        return 'policy.v1';
    }

    public function checkPolicy(ClassificationResultDto $classificationResultDto): PolicyResultDto
    {
        [$decision, $matchedRules, $reason] = $this->decide($classificationResultDto);

        return new PolicyResultDto(
            decision: $decision,
            finalRoutingDecision: $this->resolveRoute($decision, $classificationResultDto->modelRoutingDecision),
            matchedRules: $matchedRules,
            reason: $reason,
            policyVersion: $this->getVersion(),
        );
    }

    /**
     * Вердикт + сработавшие правила + причина.
     *
     * @return array{0: PolicyDecision, 1: string[], 2: string}
     */
    private function decide(ClassificationResultDto $dto): array
    {
        // Провал разбора ответа модели — ничего автоматически не выполняем.
        if ($dto->validationErrors !== null) {
            return [PolicyDecision::BLOCKED, ['classification_failed'], 'Классификация не прошла валидацию.'];
        }

        // Рискованные категории — только через ручное одобрение.
        if (in_array($dto->risk, self::RISKY, true)) {
            return [
                PolicyDecision::REQUIRES_APPROVAL,
                ['risky_category'],
                "Рискованная категория: {$dto->risk->value}.",
            ];
        }

        // Низкая уверенность модели — тоже на одобрение.
        if ($dto->confidence !== null && $dto->confidence < self::APPROVAL_THRESHOLD) {
            return [
                PolicyDecision::REQUIRES_APPROVAL,
                ['low_confidence'],
                "Низкая уверенность модели: {$dto->confidence}.",
            ];
        }

        return [PolicyDecision::ALLOWED, ['auto_allowed'], 'Безопасно для автоматической обработки.'];
    }

    /**
     * Итоговый маршрут — всегда RoutingDecision:
     * ALLOWED → маршрут модели (фолбэк MANUAL_TRIAGE), REQUIRES_APPROVAL → HUMAN_REVIEW,
     * BLOCKED → MANUAL_TRIAGE.
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
