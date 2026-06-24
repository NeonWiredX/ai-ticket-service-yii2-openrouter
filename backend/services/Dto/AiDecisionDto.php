<?php

namespace app\services\Dto;

use app\models\Enum\ClassificationStatus;

/**
 * Итоговое решение по тикету — чистый результат пайплайна (домен, без персистентности).
 * Композиция результата классификации и вердикта политики + производный статус и связь с тикетом.
 * В атрибуты AiDecision маппится через {@see self::toAiDecisionAttributes()};
 * сохранением занимается отдельный слой (репозиторий/контроллер).
 */
final class AiDecisionDto implements \JsonSerializable
{
    public function __construct(
        public readonly int $ticketId,
        public readonly ClassificationStatus $status,
        public readonly ClassificationResultDto $classification,
        public readonly PolicyResultDto $policy,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toAiDecisionAttributes(): array
    {
        return ['ticket_id' => $this->ticketId, 'status' => $this->status->value]
            + $this->classification->toAiDecisionAttributes()
            + $this->policy->toAiDecisionAttributes();
    }

    public function jsonSerialize(): array
    {
        return $this->toAiDecisionAttributes();
    }
}
