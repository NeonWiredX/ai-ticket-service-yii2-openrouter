<?php

namespace app\services\Dto;

use app\models\Enum\Category;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;

/**
 * Результат классификации тикета — чистый типизированный «конверт».
 *
 * Наполняется границей ({@see \app\services\Schema\ClassificationSchemaInterface::parse()});
 * сам НЕ валидирует. Извлекается в атрибуты AiDecision через {@see self::toAiDecisionAttributes()}.
 */
final class ClassificationResultDto implements \JsonSerializable
{
    public function __construct(
        public readonly ?Category $category,
        public readonly ?Priority $priority,
        public readonly ?Risk $risk,
        public readonly ?float $confidence,
        public readonly ?string $summary,
        public readonly ?string $reason,
        public readonly ?RoutingDecision $modelRoutingDecision,
        public readonly string $model,
        public readonly string $schemaVersion,
        public readonly string $traceId,
        public readonly ?int $latencyMs = null,
        public readonly int $retryCount = 0,
        /** @var array<string,string>|null ошибки разбора ответа модели */
        public readonly ?array $validationErrors = null,
        /** @var array<string,mixed>|null сырой ответ модели */
        public readonly ?array $rawModelOutput = null,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toAiDecisionAttributes(): array
    {
        return [
            'category' => $this->category?->value,
            'priority' => $this->priority?->value,
            'risk' => $this->risk?->value,
            'confidence' => $this->confidence,
            'summary' => $this->summary,
            'reason' => $this->reason,
            'model_routing_decision' => $this->modelRoutingDecision?->value,
            'model' => $this->model,
            'schema_version' => $this->schemaVersion,
            'trace_id' => $this->traceId,
            'latency_ms' => $this->latencyMs,
            'retry_count' => $this->retryCount,
            'validation_errors' => $this->validationErrors,
            'raw_model_output' => $this->rawModelOutput,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toAiDecisionAttributes();
    }
}
