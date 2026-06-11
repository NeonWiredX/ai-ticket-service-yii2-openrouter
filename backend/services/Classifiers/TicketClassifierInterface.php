<?php

namespace app\services\Classifiers;

use app\models\Entity\Ticket;
use app\services\Dto\ClassificationResultDto;

interface TicketClassifierInterface
{
    public function classify(Ticket $ticket): ClassificationResultDto;
}