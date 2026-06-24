<?php

namespace app\models\Entity;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Тикет, пришедший из внешней системы.
 *
 * @property int $id
 * @property string $external_id ID тикета в системе-источнике
 * @property string $tenant_id
 * @property string $user_id
 * @property string $subject
 * @property string $body
 * @property string $source канал/система-источник
 * @property string $created_at
 *
 * @property AiDecision[] $aiDecisions
 */
class Ticket extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tickets}}';
    }

    public function rules(): array
    {
        return [
            [['external_id', 'tenant_id', 'user_id', 'subject', 'body', 'source'], 'required'],
            [['subject', 'body'], 'string'],
            [['external_id', 'tenant_id', 'user_id'], 'string', 'max' => 128],
            [['source'], 'string', 'max' => 64],
            [['created_at'], 'safe'],
            // Уникальность (tenant_id, external_id) держит индекс ux_tickets_tenant_external +
            // идемпотентный приём (TicketIngestionService, INSERT ... ON CONFLICT DO NOTHING).
            // AR-правило 'unique' здесь было бы TOCTOU-racy и ломало бы идемпотентный путь
            // (validate() падал бы на уже существующем тикете).
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'external_id' => 'External ID',
            'tenant_id' => 'Tenant ID',
            'user_id' => 'User ID',
            'subject' => 'Subject',
            'body' => 'Body',
            'source' => 'Source',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Решения AI-классификатора по этому тикету.
     */
    public function getAiDecisions(): ActiveQuery
    {
        return $this->hasMany(AiDecision::class, ['ticket_id' => 'id']);
    }
}
