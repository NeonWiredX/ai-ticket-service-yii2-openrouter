<?php

use yii\db\Migration;

class m260611_212623_ticket_tables_init extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%tickets}}', [
            'id' => $this->bigPrimaryKey(),

            'external_id' => $this->string(128)->notNull(),
            'tenant_id' => $this->string(128)->notNull(),
            'user_id' => $this->string(128)->notNull(),

            'subject' => $this->text()->notNull(),
            'body' => $this->text()->notNull(),
            'source' => $this->string(64)->notNull(),

            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'ux_tickets_tenant_external',
            '{{%tickets}}',
            ['tenant_id', 'external_id'],
            true
        );

        $this->createIndex(
            'idx_tickets_tenant_created',
            '{{%tickets}}',
            ['tenant_id', 'created_at']
        );

        $this->createIndex(
            'idx_tickets_source',
            '{{%tickets}}',
            'source'
        );

        $this->createTable('{{%ai_decisions}}', [
            'id' => $this->bigPrimaryKey(),

            'ticket_id' => $this->bigInteger()->notNull(),

            'schema_version' => $this->string(32)->notNull(),
            'policy_version' => $this->string(32)->notNull(),
            'model' => $this->string(128)->notNull(),

            'status' => $this->string(64)->notNull(),

            'category' => $this->string(64)->null(),
            'priority' => $this->string(64)->null(),
            'risk' => $this->string(64)->null(),
            'confidence' => $this->decimal(5, 4)->null(),

            'summary' => $this->text()->null(),
            'reason' => $this->text()->null(),

            'model_routing_decision' => $this->string(128)->null(),
            'final_routing_decision' => $this->string(128)->null(),
            'policy_decision' => $this->string(128)->null(),

            'executable_actions_allowed' => $this->boolean()->notNull()->defaultValue(false),

            'matched_rules' => "jsonb NOT NULL DEFAULT '[]'::jsonb",
            'validation_errors' => 'jsonb NULL',
            'raw_model_output' => 'jsonb NULL',
            'retry_count' => $this->integer()->notNull()->defaultValue(0),
            'latency_ms' => $this->integer()->null(),

            'trace_id' => $this->string(64)->notNull(),

            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addForeignKey(
            'fk_ai_decisions_ticket',
            '{{%ai_decisions}}',
            'ticket_id',
            '{{%tickets}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->createIndex(
            'idx_ai_decisions_ticket',
            '{{%ai_decisions}}',
            'ticket_id'
        );

        $this->createIndex(
            'idx_ai_decisions_status',
            '{{%ai_decisions}}',
            'status'
        );

        $this->createIndex(
            'idx_ai_decisions_category',
            '{{%ai_decisions}}',
            'category'
        );

        $this->createIndex(
            'idx_ai_decisions_risk',
            '{{%ai_decisions}}',
            'risk'
        );

        $this->createIndex(
            'idx_ai_decisions_final_route',
            '{{%ai_decisions}}',
            'final_routing_decision'
        );

        $this->createIndex(
            'idx_ai_decisions_trace',
            '{{%ai_decisions}}',
            'trace_id'
        );

        $this->createIndex(
            'idx_ai_decisions_created',
            '{{%ai_decisions}}',
            'created_at'
        );

        $this->execute(
            'ALTER TABLE {{%ai_decisions}}
             ADD CONSTRAINT chk_ai_decisions_confidence_range
             CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))'
        );

        $this->execute(
            'ALTER TABLE {{%ai_decisions}}
             ADD CONSTRAINT chk_ai_decisions_retry_count_non_negative
             CHECK (retry_count >= 0)'
        );

        $this->execute(
            'ALTER TABLE {{%ai_decisions}}
             ADD CONSTRAINT chk_ai_decisions_latency_ms_non_negative
             CHECK (latency_ms IS NULL OR latency_ms >= 0)'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%ai_decisions}}');
        $this->dropTable('{{%tickets}}');
    }
}
