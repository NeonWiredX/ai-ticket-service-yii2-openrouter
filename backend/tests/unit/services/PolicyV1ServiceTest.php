<?php

namespace tests\unit\services;

use app\models\Enum\PolicyDecision;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;
use app\services\Dto\PolicyResultDto;
use app\services\Policy\PolicyV1Service;

class PolicyV1ServiceTest extends \Codeception\Test\Unit
{
    /**
     * @param array<string,mixed> $modelOutput
     */
    private function check(array $modelOutput): PolicyResultDto
    {
        $dto = ClassificationResultDto::fromModelOutput($modelOutput, model: 'm', schemaVersion: 'v1', traceId: 't');

        return (new PolicyV1Service())->checkPolicy($dto);
    }

    public function testFailedParsingIsBlockedAndRoutedToTriage(): void
    {
        // битый enum → validationErrors → BLOCKED
        $result = $this->check(['category' => 'garbage']);

        $this->assertSame(PolicyDecision::BLOCKED, $result->decision);
        $this->assertSame(RoutingDecision::MANUAL_TRIAGE, $result->finalRoutingDecision);
        $this->assertContains('classification_failed', $result->matchedRules);
    }

    public function testRiskyRiskRequiresApprovalAndHumanReview(): void
    {
        $result = $this->check(['risk' => 'security', 'confidence' => 0.99]);

        $this->assertSame(PolicyDecision::REQUIRES_APPROVAL, $result->decision);
        $this->assertSame(RoutingDecision::HUMAN_REVIEW, $result->finalRoutingDecision);
        $this->assertContains('risky_category', $result->matchedRules);
    }

    public function testLowConfidenceRequiresApproval(): void
    {
        $result = $this->check(['risk' => 'none', 'confidence' => 0.3]);

        $this->assertSame(PolicyDecision::REQUIRES_APPROVAL, $result->decision);
        $this->assertContains('low_confidence', $result->matchedRules);
    }

    public function testSafeHighConfidenceIsAllowedAndKeepsModelRoute(): void
    {
        $result = $this->check(['risk' => 'none', 'confidence' => 0.95, 'routing_decision' => 'support_queue']);

        $this->assertSame(PolicyDecision::ALLOWED, $result->decision);
        $this->assertSame(RoutingDecision::SUPPORT_QUEUE, $result->finalRoutingDecision);
        $this->assertContains('auto_allowed', $result->matchedRules);
    }

    public function testResultCarriesPolicyVersion(): void
    {
        $this->assertSame(
            (new PolicyV1Service())->getVersion(),
            $this->check(['risk' => 'none', 'confidence' => 0.95])->policyVersion,
        );
    }
}
