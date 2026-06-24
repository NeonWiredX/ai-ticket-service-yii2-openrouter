<?php

namespace tests\unit\services;

use app\models\Enum\Category;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;

class ClassificationResultDtoTest extends \Codeception\Test\Unit
{
    public function testToAiDecisionAttributesUsesBackingValues(): void
    {
        $dto = new ClassificationResultDto(
            category: Category::BILLING,
            priority: Priority::HIGH,
            risk: Risk::NONE,
            confidence: 0.87,
            summary: 'sum',
            reason: 'rsn',
            modelRoutingDecision: RoutingDecision::SUPPORT_QUEUE,
            model: 'fake',
            schemaVersion: 'classification.v1',
            traceId: 'tt',
        );

        $attrs = $dto->toAiDecisionAttributes();

        $this->assertSame('billing', $attrs['category']);
        $this->assertSame('high', $attrs['priority']);
        $this->assertSame('none', $attrs['risk']);
        $this->assertSame('support_queue', $attrs['model_routing_decision']);
        $this->assertSame('classification.v1', $attrs['schema_version']);
        $this->assertSame('fake', $attrs['model']);
        // DTO несёт только поля классификатора — не статус и не поля политики
        $this->assertArrayNotHasKey('status', $attrs);
        $this->assertArrayNotHasKey('policy_decision', $attrs);
    }

    public function testNullEnumsSerializeToNull(): void
    {
        $dto = new ClassificationResultDto(
            category: null,
            priority: null,
            risk: null,
            confidence: null,
            summary: null,
            reason: null,
            modelRoutingDecision: null,
            model: 'm',
            schemaVersion: 'classification.v1',
            traceId: 't',
        );

        $attrs = $dto->toAiDecisionAttributes();

        $this->assertNull($attrs['category']);
        $this->assertNull($attrs['model_routing_decision']);
        $this->assertNull($attrs['confidence']);
    }
}
