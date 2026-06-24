<?php

namespace app\services\Dto;

use app\models\Entity\Ticket;

/**
 * Результат приёма тикета: сам тикет и был ли он создан сейчас
 * (false — дубль, отдан уже существующий).
 */
final class IngestResult
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly bool $wasCreated,
    ) {
    }
}
