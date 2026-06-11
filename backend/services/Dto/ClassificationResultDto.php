<?php

namespace app\services\Dto;

use app\models\Enum\Category;
use app\models\Enum\ClassificationStatus;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;

/**
 * Результат классификации тикета — типизированный «конверт» ответа модели.
 *
 * Заполняется на границе через {@see self::fromModelOutput()} (сырой JSON → типы
 * + сбор ошибок), извлекается в атрибуты AiDecision через {@see self::toAiDecisionAttributes()}.
 * Поля политики (policy_*, final_routing_decision, executable_actions_allowed) сюда не входят —
 * их проставляет сервис.
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

    public static function fromModelOutput(
        array $output,
        string $model,
        string $schemaVersion,
        string $traceId,
        ?int $latencyMs = null,
        int $retryCount = 0,
    ): self {
        $errors = [];

        $category = self::toEnum(Category::class, $output['category'] ?? null, 'category', $errors);
        $priority = self::toEnum(Priority::class, $output['priority'] ?? null, 'priority', $errors);
        $risk = self::toEnum(Risk::class, $output['risk'] ?? null, 'risk', $errors);
        $routing = self::toEnum(RoutingDecision::class, $output['routing_decision'] ?? null, 'routing_decision', $errors);

        $confidence = $output['confidence'] ?? null;
        if ($confidence !== null && !is_numeric($confidence)) {
            $errors['confidence'] = 'ожидалось число';
            $confidence = null;
        }

        return new self(
            category: $category,
            priority: $priority,
            risk: $risk,
            confidence: $confidence !== null ? (float) $confidence : null,
            summary: isset($output['summary']) ? (string) $output['summary'] : null,
            reason: isset($output['reason']) ? (string) $output['reason'] : null,
            modelRoutingDecision: $routing,
            model: $model,
            schemaVersion: $schemaVersion,
            traceId: $traceId,
            latencyMs: $latencyMs,
            retryCount: $retryCount,
            validationErrors: $errors ?: null,
            rawModelOutput: $output,
        );
    }

    /**
     * Извлечение в атрибуты AiDecision (enum → backing value) для `$ar->load(..., '')`.
     *
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

    /**
     * @param class-string<\BackedEnum> $enum
     * @param array<string,string> $errors
     */
    private static function toEnum(string $enum, mixed $value, string $field, array &$errors): ?\BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        $case = $enum::tryFrom((string) $value);
        if ($case === null) {
            $errors[$field] = "недопустимое значение: {$value}";
        }

        return $case;
    }
}
