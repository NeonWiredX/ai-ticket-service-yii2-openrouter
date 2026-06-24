<?php

namespace app\services\Dto;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;

/**
 * Результат обработки тикета: принятый тикет и итоговое решение по нему.
 * skipped = true — решение уже существовало (повтор), заново не классифицировали.
 */
final class TicketProcessingResult
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly AiDecision $decision,
        public readonly bool $skipped,
    ) {
    }
}
