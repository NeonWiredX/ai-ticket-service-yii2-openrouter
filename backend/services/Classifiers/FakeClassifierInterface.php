<?php

namespace app\services\Classifiers;

use app\models\Entity\Ticket;
use app\services\Dto\ClassificationResultDto;

class FakeClassifierInterface implements TicketClassifierInterface
{

    public function classify(Ticket $ticket): ClassificationResultDto
    {
        // TODO: Implement classify() method.
    }
}