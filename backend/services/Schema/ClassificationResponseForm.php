<?php

namespace app\services\Schema;

use yii\base\Model;

/**
 * Форма валидации сырого ответа классификатора по контракту v1 (деталь реализации).
 *
 * Атрибуты намеренно нетипизированы — вход недоверенный, кривые типы ловит валидация,
 * а не TypeError на load(). Правила и набор полей — единый источник {@see ClassificationSchemaV1}.
 */
class ClassificationResponseForm extends Model
{
    public $category;
    public $priority;
    public $risk;
    public $routing_decision;
    public $confidence;
    public $summary;
    public $reason;

    public function rules(): array
    {
        $rules = [
            [ClassificationSchemaV1::REQUIRED, 'required'],
            ['confidence', 'number', 'min' => 0, 'max' => 1],
            [['summary', 'reason'], 'string'],
        ];

        foreach (ClassificationSchemaV1::ENUMS as $field => $enum) {
            $rules[] = [$field, 'in', 'range' => ClassificationSchemaV1::enumValues($enum)];
        }

        return $rules;
    }
}
