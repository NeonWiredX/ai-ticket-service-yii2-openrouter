<?php

namespace app\services\Classifiers;

use app\models\Entity\Ticket;
use app\models\Enum\Category;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use app\services\Dto\ClassificationResultDto;

/**
 * Заглушка классификатора: возвращает случайный результат вместо вызова модели.
 * Собирает «сырой ответ» и прогоняет его через гидрацию DTO — как настоящий.
 */
class FakeClassifierInterface implements TicketClassifierInterface
{
    public function classify(Ticket $ticket): ClassificationResultDto
    {
        $output = [
            'category' => $this->pickEnum(Category::class),
            'priority' => $this->pickEnum(Priority::class),
            'risk' => $this->pickEnum(Risk::class),
            'routing_decision' => $this->pickEnum(RoutingDecision::class),
            'confidence' => round(random_int(5000, 10000) / 10000, 4),
            'summary' => "Обращение по теме «{$ticket->subject}».",
            'reason' => 'Классифицировано фейковым классификатором (демо-режим).',
        ];

        return ClassificationResultDto::fromModelOutput(
            output: $output,
            model: 'fake-classifier',
            schemaVersion: 'v1',
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

        return $cases[array_rand($cases)]->value;
    }
}
