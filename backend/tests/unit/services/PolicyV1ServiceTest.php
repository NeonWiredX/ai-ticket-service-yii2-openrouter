<?php

namespace tests\unit\services;

use app\models\Enum\Category;
use app\models\Enum\PolicyDecision;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;
use app\services\Dto\PolicyResultDto;
use app\services\Policy\PolicyV1Service;

class PolicyV1ServiceTest extends \Codeception\Test\Unit
{
    /**
     * @param array<string,string>|null $validationErrors
     */
    private function dto(
        ?Risk $risk = Risk::NONE,
        ?float $confidence = 0.9,
        ?RoutingDecision $route = RoutingDecision::SUPPORT_QUEUE,
        ?array $validationErrors = null,
    ): ClassificationResultDto {
        return new ClassificationResultDto(
            category: Category::GENERAL,
            priority: Priority::LOW,
            risk: $risk,
            confidence: $confidence,
            summary: null,
            reason: null,
            modelRoutingDecision: $route,
            model: 'm',
            schemaVersion: 'classification.v1',
            traceId: 't',
            validationErrors: $validationErrors,
        );
    }

    private function check(ClassificationResultDto $dto): PolicyResultDto
    {
        return (new PolicyV1Service())->checkPolicy($dto);
    }

    public function testFailedParsingIsBlockedAndRoutedToTriage(): void
    {
        $result = $this->check($this->dto(validationErrors: ['category' => 'bad']));

        $this->assertSame(PolicyDecision::BLOCKED, $result->decision);
        $this->assertSame(RoutingDecision::MANUAL_TRIAGE, $result->finalRoutingDecision);
        $this->assertContains('classification_failed', $result->matchedRules);
    }

    public function testRiskyRiskRequiresApprovalAndHumanReview(): void
    {
        $result = $this->check($this->dto(risk: Risk::SECURITY, confidence: 0.99));

        $this->assertSame(PolicyDecision::REQUIRES_APPROVAL, $result->decision);
        $this->assertSame(RoutingDecision::HUMAN_REVIEW, $result->finalRoutingDecision);
        $this->assertContains('risky_category', $result->matchedRules);
    }

    public function testLowConfidenceRequiresApproval(): void
    {
        $result = $this->check($this->dto(risk: Risk::NONE, confidence: 0.3));

        $this->assertSame(PolicyDecision::REQUIRES_APPROVAL, $result->decision);
        $this->assertContains('low_confidence', $result->matchedRules);
    }

    public function testSafeHighConfidenceIsAllowedAndKeepsModelRoute(): void
    {
        $result = $this->check($this->dto(risk: Risk::NONE, confidence: 0.95, route: RoutingDecision::SUPPORT_QUEUE));

        $this->assertSame(PolicyDecision::ALLOWED, $result->decision);
        $this->assertSame(RoutingDecision::SUPPORT_QUEUE, $result->finalRoutingDecision);
        $this->assertContains('auto_allowed', $result->matchedRules);
    }

    public function testResultCarriesPolicyVersion(): void
    {
        $this->assertSame(
            (new PolicyV1Service())->getVersion(),
            $this->check($this->dto())->policyVersion,
        );
    }
}
