<?php

namespace tests\unit\services;

use app\models\Enum\Category;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;

class ClassificationResultDtoTest extends \Codeception\Test\Unit
{
    public function testValidOutputParsesIntoEnums(): void
    {
        $dto = ClassificationResultDto::fromModelOutput(
            [
                'category' => 'billing',
                'priority' => 'high',
                'risk' => 'none',
                'routing_decision' => 'support_queue',
                'confidence' => 0.87,
                'summary' => 's',
                'reason' => 'r',
            ],
            model: 'm',
            schemaVersion: 'v1',
            traceId: 't1',
        );

        $this->assertNull($dto->validationErrors);
        $this->assertSame(Category::BILLING, $dto->category);
        $this->assertSame(Priority::HIGH, $dto->priority);
        $this->assertSame(Risk::NONE, $dto->risk);
        $this->assertSame(RoutingDecision::SUPPORT_QUEUE, $dto->modelRoutingDecision);
        $this->assertSame(0.87, $dto->confidence);
    }

    public function testInvalidValuesAreCollectedNotThrown(): void
    {
        $dto = ClassificationResultDto::fromModelOutput(
            ['category' => 'nope', 'risk' => 'low', 'confidence' => 'NaN'],
            model: 'm',
            schemaVersion: 'v1',
            traceId: 't2',
        );

        $this->assertNull($dto->category);
        $this->assertNull($dto->risk);
        $this->assertNull($dto->confidence);
        $this->assertSame(
            ['category', 'risk', 'confidence'],
            array_keys($dto->validationErrors),
        );
    }

    public function testMissingFieldsAreNullWithoutErrors(): void
    {
        $dto = ClassificationResultDto::fromModelOutput([], model: 'm', schemaVersion: 'v1', traceId: 't3');

        $this->assertNull($dto->validationErrors);
        $this->assertNull($dto->category);
        $this->assertNull($dto->confidence);
    }

    public function testToAiDecisionAttributesUsesBackingValues(): void
    {
        $dto = ClassificationResultDto::fromModelOutput(
            [
                'category' => 'security',
                'priority' => 'low',
                'risk' => 'privacy',
                'routing_decision' => 'human_review',
                'confidence' => 0.5,
            ],
            model: 'fake',
            schemaVersion: 'v1',
            traceId: 'tt',
        );

        $attrs = $dto->toAiDecisionAttributes();

        $this->assertSame('security', $attrs['category']);
        $this->assertSame('low', $attrs['priority']);
        $this->assertSame('privacy', $attrs['risk']);
        $this->assertSame('human_review', $attrs['model_routing_decision']);
        $this->assertSame('fake', $attrs['model']);
        $this->assertSame('tt', $attrs['trace_id']);
        $this->assertArrayNotHasKey('status', $attrs);
    }
}
