<?php

namespace app\services\Policy;

use app\models\Enum\PolicyDecision;
use app\services\Dto\ClassificationResultDto;

interface TicketPolicyInterface
{
    public function getVersion(): string;

    public function checkPolicy(ClassificationResultDto $classificationResultDto): PolicyDecision;
}