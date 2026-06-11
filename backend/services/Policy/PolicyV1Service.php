<?php

namespace app\services\Policy;

use app\models\Enum\PolicyDecision;
use app\services\Dto\ClassificationResultDto;

class PolicyV1Service implements TicketPolicyInterface
{
    public function getVersion(): string
    {
        return 'v1';
    }

    public function checkPolicy(ClassificationResultDto $classificationResultDto): PolicyDecision
    {

    }
}