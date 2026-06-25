<?php

use yii\db\Migration;

class m260625_114759_add_prompt_version_to_ai_decisions extends Migration
{
    public function safeUp()
    {
        // DEFAULT нужен для бэкфилла существующих строк; колонка NOT NULL как schema/policy_version.
        // PostgreSQL не поддерживает AFTER — колонка добавляется в конец, порядок роли не играет.
        $this->addColumn(
            '{{%ai_decisions}}',
            'prompt_version',
            $this->string(32)->notNull()->defaultValue('prompt.v1')
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%ai_decisions}}', 'prompt_version');
    }
}
