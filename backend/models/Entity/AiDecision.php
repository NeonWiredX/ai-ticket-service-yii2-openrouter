<?php

namespace app\models\Entity;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Решение AI-классификатора по тикету.
 *
 * jsonb-поля (matched_rules, validation_errors, raw_model_output) Yii под PostgreSQL
 * читает/пишет как PHP-массивы автоматически.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $schema_version версия схемы ответа модели
 * @property string $policy_version версия политики маршрутизации
 * @property string $prompt_version версия промпта классификации
 * @property string $model идентификатор использованной модели
 * @property string $status статус обработки решения
 * @property string|null $category
 * @property string|null $priority
 * @property string|null $risk
 * @property float|null $confidence уверенность модели, 0..1
 * @property string|null $summary
 * @property string|null $reason
 * @property string|null $model_routing_decision маршрут, предложенный моделью
 * @property string|null $final_routing_decision итоговый маршрут после политики
 * @property string|null $policy_decision вердикт политики
 * @property bool $executable_actions_allowed разрешены ли исполняемые действия
 * @property array $matched_rules сработавшие правила политики
 * @property array|null $validation_errors ошибки валидации ответа модели
 * @property array|null $raw_model_output сырой ответ модели
 * @property int $retry_count
 * @property int|null $latency_ms длительность запроса к модели, мс
 * @property string $trace_id
 * @property string $created_at
 *
 * @property Ticket $ticket
 */
class AiDecision extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_decisions}}';
    }

    public function rules(): array
    {
        return [
            [['ticket_id', 'schema_version', 'policy_version', 'prompt_version', 'model', 'status', 'trace_id'], 'required'],
            [['ticket_id'], 'integer'],
            [['retry_count', 'latency_ms'], 'integer', 'min' => 0],
            [['confidence'], 'number', 'min' => 0, 'max' => 1],
            [['executable_actions_allowed'], 'boolean'],
            [['summary', 'reason'], 'string'],
            [['matched_rules', 'validation_errors', 'raw_model_output'], 'safe'],
            [['schema_version', 'policy_version', 'prompt_version'], 'string', 'max' => 32],
            [['model', 'model_routing_decision', 'final_routing_decision', 'policy_decision'], 'string', 'max' => 128],
            [['status', 'category', 'priority', 'risk'], 'string', 'max' => 64],
            [['trace_id'], 'string', 'max' => 64],
            [['executable_actions_allowed'], 'default', 'value' => false],
            [['retry_count'], 'default', 'value' => 0],
            [['matched_rules'], 'default', 'value' => []],
            [['created_at'], 'safe'],
            [
                ['ticket_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Ticket::class,
                'targetAttribute' => ['ticket_id' => 'id'],
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'ticket_id' => 'Ticket ID',
            'schema_version' => 'Schema Version',
            'policy_version' => 'Policy Version',
            'model' => 'Model',
            'status' => 'Status',
            'category' => 'Category',
            'priority' => 'Priority',
            'risk' => 'Risk',
            'confidence' => 'Confidence',
            'summary' => 'Summary',
            'reason' => 'Reason',
            'model_routing_decision' => 'Model Routing Decision',
            'final_routing_decision' => 'Final Routing Decision',
            'policy_decision' => 'Policy Decision',
            'executable_actions_allowed' => 'Executable Actions Allowed',
            'matched_rules' => 'Matched Rules',
            'validation_errors' => 'Validation Errors',
            'raw_model_output' => 'Raw Model Output',
            'retry_count' => 'Retry Count',
            'latency_ms' => 'Latency (ms)',
            'trace_id' => 'Trace ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Тикет, к которому относится решение.
     */
    public function getTicket(): ActiveQuery
    {
        return $this->hasOne(Ticket::class, ['id' => 'ticket_id']);
    }
}
