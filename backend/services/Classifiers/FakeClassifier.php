<?php

namespace app\services\Classifiers;

use app\models\Entity\Ticket;
use app\models\Enum\Category;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;
use app\services\Prompt\TicketPromptInterface;
use app\services\Schema\ClassificationSchemaInterface;

/**
 * Заглушка классификатора: вместо вызова модели генерит случайный «сырой ответ»
 * и прогоняет его через переданную схему-границу — ровно тот путь, что у настоящего.
 */
class FakeClassifier implements TicketClassifierInterface
{
    public function classify(Ticket $ticket, ClassificationSchemaInterface $schema, TicketPromptInterface $prompt): ClassificationResultDto
    {
        $rawOutput = [
            'category' => $this->pickEnum(Category::class),
            'priority' => $this->pickEnum(Priority::class),
            'risk' => $this->pickEnum(Risk::class),
            'routing_decision' => $this->pickEnum(RoutingDecision::class),
            'confidence' => round(random_int(5000, 10000) / 10000, 4),
            'summary' => "Обращение по теме «{$ticket->subject}».",
            'reason' => 'Классифицировано фейковым классификатором (демо-режим).',
        ];

        return $schema->parse(
            $rawOutput,
            model: 'fake-classifier',
            promptVersion: $prompt->getVersion(),
            traceId: 'trace-' . bin2hex(random_bytes(8)),
            latencyMs: random_int(50, 800),
        );
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    private function pickEnum(string $enum): string
    {
        $cases = $enum::cases();

        return (string) $cases[array_rand($cases)]->value;
    }
}
