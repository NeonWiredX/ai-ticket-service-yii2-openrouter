<?php

namespace app\services\Dto;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;

/**
 * Результат обработки тикета: принятый тикет и итоговое решение по нему.
 * classificationSkipped = true — решение уже существовало → классификацию не запускали.
 */
final class TicketProcessingResult
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly AiDecision $decision,
        public readonly bool $classificationSkipped,
    ) {
    }
}
