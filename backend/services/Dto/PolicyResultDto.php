<?php

namespace app\services\Dto;

use app\models\Enum\PolicyDecision;
use app\models\Enum\RoutingDecision;

/**
 * Результат применения политики к классификации.
 * Извлекается в «политические» поля AiDecision через {@see self::toAiDecisionAttributes()}.
 */
final class PolicyResultDto implements \JsonSerializable
{
    /**
     * @param string[] $matchedRules сработавшие правила политики
     */
    public function __construct(
        public readonly PolicyDecision $decision,
        public readonly RoutingDecision $finalRoutingDecision,
        public readonly array $matchedRules,
        public readonly string $reason,
        public readonly string $policyVersion,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toAiDecisionAttributes(): array
    {
        return [
            'policy_decision' => $this->decision->value,
            'final_routing_decision' => $this->finalRoutingDecision->value,
            'matched_rules' => $this->matchedRules,
            'policy_version' => $this->policyVersion,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toAiDecisionAttributes();
    }
}
