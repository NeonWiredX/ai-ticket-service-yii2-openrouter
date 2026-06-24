<?php

namespace tests\unit\services;

use app\models\Enum\Category;
use app\services\Dto\ClassificationResultDto;
use app\services\Schema\ClassificationSchemaV1;

class ClassificationSchemaV1Test extends \Codeception\Test\Unit
{
    private const VALID = [
        'category' => 'billing',
        'priority' => 'high',
        'risk' => 'none',
        'routing_decision' => 'support_queue',
        'confidence' => 0.9,
        'summary' => 's',
        'reason' => 'r',
    ];

    /**
     * @param array<string,mixed> $raw
     */
    private function parse(array $raw): ClassificationResultDto
    {
        return (new ClassificationSchemaV1())->parse($raw, model: 'm', traceId: 't');
    }

    public function testValidOutputParsesWithoutErrors(): void
    {
        $dto = $this->parse(self::VALID);

        $this->assertNull($dto->validationErrors);
        $this->assertSame(Category::BILLING, $dto->category);
        $this->assertSame(0.9, $dto->confidence);
        $this->assertSame('classification.v1', $dto->schemaVersion);
    }

    public function testInvalidEnumIsFlaggedAndNulled(): void
    {
        $dto = $this->parse(['category' => 'nonsense'] + self::VALID);

        $this->assertNull($dto->category);
        $this->assertArrayHasKey('category', $dto->validationErrors);
    }

    public function testMissingRequiredIsFlagged(): void
    {
        $raw = self::VALID;
        unset($raw['risk']);

        $dto = $this->parse($raw);

        $this->assertArrayHasKey('risk', $dto->validationErrors);
    }

    public function testConfidenceOutOfRangeIsFlagged(): void
    {
        $dto = $this->parse(['confidence' => 2] + self::VALID);

        $this->assertArrayHasKey('confidence', $dto->validationErrors);
    }

    public function testSchemaEnumsMatchEnumCases(): void
    {
        $schema = (new ClassificationSchemaV1())->getSchema();

        $this->assertSame(
            array_map(static fn ($c) => $c->value, Category::cases()),
            $schema['properties']['category']['enum'],
        );
        $this->assertContains('category', $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function testGetJsonIsValidJson(): void
    {
        $decoded = json_decode((new ClassificationSchemaV1())->getJson(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('object', $decoded['type']);
    }
}
