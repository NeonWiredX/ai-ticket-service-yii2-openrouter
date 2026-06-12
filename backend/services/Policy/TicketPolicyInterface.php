<?php

namespace app\services\Policy;

use app\services\Dto\ClassificationResultDto;
use app\services\Dto\PolicyResultDto;

interface TicketPolicyInterface
{
    public function getVersion(): string;

    public function checkPolicy(ClassificationResultDto $classificationResultDto): PolicyResultDto;
}
