<?php

namespace app\services\Dto;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;

/**
 * Результат обработки тикета: принятый тикет и итоговое решение по нему.
 * classificationSkipped = true — решение уже существовало (повтор), заново не классифицировали.
 * {@see self::jsonSerialize()} — стабильная форма для вывода (общий контракт CLI и HTTP).
 */
final class TicketProcessingResult implements \JsonSerializable
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly AiDecision $decision,
        public readonly bool $classificationSkipped,
    ) {
    }

    /**
     * Стабильная форма: фиксированный набор и порядок полей; типы приведены явно
     * (pgsql может вернуть строки).
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $decision = $this->decision;

        return [
            'ticket_id' => (int) $this->ticket->id,
            'classification_skipped' => $this->classificationSkipped,
            'decision' => [
                'id' => (int) $decision->id,
                'status' => $decision->status,
                'category' => $decision->category,
                'priority' => $decision->priority,
                'risk' => $decision->risk,
                'confidence' => $decision->confidence === null ? null : (float) $decision->confidence,
                'policy_decision' => $decision->policy_decision,
                'final_routing_decision' => $decision->final_routing_decision,
                'executable_actions_allowed' => (bool) $decision->executable_actions_allowed,
                'matched_rules' => $decision->matched_rules,
                'model' => $decision->model,
                'schema_version' => $decision->schema_version,
                'policy_version' => $decision->policy_version,
                'prompt_version' => $decision->prompt_version,
                'trace_id' => $decision->trace_id,
            ],
        ];
    }
}
