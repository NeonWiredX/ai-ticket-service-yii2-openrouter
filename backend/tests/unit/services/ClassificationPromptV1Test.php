<?php

namespace tests\unit\services;

use app\models\Entity\Ticket;
use app\services\Prompt\ClassificationPromptV1;

class ClassificationPromptV1Test extends \Codeception\Test\Unit
{
    public function testVersion(): void
    {
        $this->assertSame('prompt.v1', (new ClassificationPromptV1())->getVersion());
    }

    public function testSystemPromptInstructsJsonSchemaOutput(): void
    {
        $this->assertStringContainsString('JSON-схеме', (new ClassificationPromptV1())->getSystemPrompt());
    }

    public function testUserPromptContainsTicketSubjectAndBody(): void
    {
        // in-memory тикет с переопределённым attributes(), чтобы AR не уходил в схему БД
        // (даже new Ticket([...]) через config-конструктор лезет в getTableSchema()).
        $ticket = new class extends Ticket {
            public function attributes(): array
            {
                return ['id', 'external_id', 'tenant_id', 'user_id', 'subject', 'body', 'source', 'created_at'];
            }
        };
        $ticket->subject = 'SUBJ-MARKER';
        $ticket->body = 'BODY-MARKER';

        $rendered = (new ClassificationPromptV1())->renderUserPrompt($ticket);

        $this->assertStringContainsString('SUBJ-MARKER', $rendered);
        $this->assertStringContainsString('BODY-MARKER', $rendered);
    }
}
