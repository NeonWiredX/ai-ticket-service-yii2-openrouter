<?php

namespace app\services\Schema;

use app\models\Enum\Category;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;

/**
 * Контракт ответа классификатора, версия 1 (граница, stateless).
 *
 * Единый источник правды: {@see self::ENUMS}/{@see self::REQUIRED} питают и JSON Schema наружу
 * ({@see self::getSchema()} — для structured output модели), и валидацию входящего ответа
 * ({@see ClassificationResponseForm::rules()}). {@see self::parse()} тем же контрактом разбирает
 * сырой ответ и собирает DTO (с validationErrors при несоответствии — без исключений).
 */
class ClassificationSchemaV1 implements ClassificationSchemaInterface
{
    public const VERSION = 'classification.v1';

    /**
     * Поля-перечисления и их типы — источник и для enum-диапазонов валидации, и для JSON Schema.
     *
     * @var array<string, class-string<\BackedEnum>>
     */
    public const ENUMS = [
        'category' => Category::class,
        'priority' => Priority::class,
        'risk' => Risk::class,
        'routing_decision' => RoutingDecision::class,
    ];

    /** @var string[] обязательные поля ответа модели */
    public const REQUIRED = ['category', 'priority', 'risk', 'routing_decision', 'confidence'];

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function getSchema(): array
    {
        $properties = [];
        foreach (self::ENUMS as $field => $enum) {
            $properties[$field] = ['type' => 'string', 'enum' => self::enumValues($enum)];
        }
        // без minimum/maximum: не все провайдеры structured output поддерживают диапазоны на number
        // (напр. Amazon Bedrock отвергает). Границу 0..1 валидируем на своей стороне в ResponseForm::rules().
        $properties['confidence'] = ['type' => 'number'];
        $properties['summary'] = ['type' => 'string'];
        $properties['reason'] = ['type' => 'string'];

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => self::REQUIRED,
            'properties' => $properties,
        ];
    }

    public function getJson(): string
    {
        return json_encode($this->getSchema(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function parse(
        array $rawOutput,
        string $model,
        string $promptVersion,
        string $traceId,
        ?int $latencyMs = null,
        int $retryCount = 0,
    ): ClassificationResultDto {
        $form = new ClassificationResponseForm();
        $form->load($rawOutput, '');
        $form->validate();

        return new ClassificationResultDto(
            category: self::toEnum(Category::class, $form->category),
            priority: self::toEnum(Priority::class, $form->priority),
            risk: self::toEnum(Risk::class, $form->risk),
            confidence: is_numeric($form->confidence) ? (float) $form->confidence : null,
            summary: is_string($form->summary) ? $form->summary : null,
            reason: is_string($form->reason) ? $form->reason : null,
            modelRoutingDecision: self::toEnum(RoutingDecision::class, $form->routing_decision),
            model: $model,
            schemaVersion: self::VERSION,
            promptVersion: $promptVersion,
            traceId: $traceId,
            latencyMs: $latencyMs,
            retryCount: $retryCount,
            validationErrors: $form->getFirstErrors() ?: null,
            rawModelOutput: $rawOutput,
        );
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @return string[]
     */
    public static function enumValues(string $enum): array
    {
        return array_map(static fn (\BackedEnum $c): string => (string) $c->value, $enum::cases());
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    private static function toEnum(string $enum, mixed $value): ?\BackedEnum
    {
        return is_scalar($value) && $value !== '' ? $enum::tryFrom((string) $value) : null;
    }
}
