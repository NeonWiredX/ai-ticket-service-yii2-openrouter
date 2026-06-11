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
 * @property AiDecisions[] $aiDecisions
 */
class Tickets extends ActiveRecord
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
            [
                ['tenant_id', 'external_id'],
                'unique',
                'targetAttribute' => ['tenant_id', 'external_id'],
                'message' => 'Тикет с таким external_id уже существует в этом tenant.',
            ],
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
        return $this->hasMany(AiDecisions::class, ['ticket_id' => 'id']);
    }
}
