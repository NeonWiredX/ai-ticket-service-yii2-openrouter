<?php

namespace tests\unit\services;

use app\models\Enum\PolicyDecision;
use app\services\Dto\ClassificationResultDto;
use app\services\Policy\PolicyV1Service;

class PolicyV1ServiceTest extends \Codeception\Test\Unit
{
    private function check(array $modelOutput): PolicyDecision
    {
        $dto = ClassificationResultDto::fromModelOutput($modelOutput, model: 'm', schemaVersion: 'v1', traceId: 't');

        return (new PolicyV1Service())->checkPolicy($dto);
    }

    public function testFailedParsingIsBlocked(): void
    {
        // битый enum → validationErrors → BLOCKED
        $this->assertSame(PolicyDecision::BLOCKED, $this->check(['category' => 'garbage']));
    }

    public function testRiskyRiskRequiresApproval(): void
    {
        $this->assertSame(
            PolicyDecision::REQUIRES_APPROVAL,
            $this->check(['risk' => 'security', 'confidence' => 0.99]),
        );
    }

    public function testLowConfidenceRequiresApproval(): void
    {
        $this->assertSame(
            PolicyDecision::REQUIRES_APPROVAL,
            $this->check(['risk' => 'none', 'confidence' => 0.3]),
        );
    }

    public function testSafeHighConfidenceIsAllowed(): void
    {
        $this->assertSame(
            PolicyDecision::ALLOWED,
            $this->check(['risk' => 'none', 'confidence' => 0.95]),
        );
    }

    public function testVersion(): void
    {
        $this->assertSame('v1', (new PolicyV1Service())->getVersion());
    }
}
