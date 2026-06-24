<?php

namespace app\services;

use app\models\Entity\Ticket;
use app\models\Enum\ClassificationStatus;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Dto\AiDecisionDto;
use app\services\Exceptions\ClassifierException;
use app\services\Policy\TicketPolicyInterface;
use app\services\Schema\ClassificationSchemaInterface;

class TicketClassificationService
{
    public function __construct(
        protected TicketClassifierInterface     $classifier,
        protected TicketPolicyInterface         $policy,
        protected ClassificationSchemaInterface $schema,
    )
    {
    }

    /**
     * Классифицирует уже сохранённый тикет и возвращает решение как DTO (без персистентности).
     * Тикет должен быть персистентным — нужен $ticket->id для связи решения.
     * Сохранением занимается отдельный слой (репозиторий/контроллер).
     *
     * @throws ClassifierException при сбое самого вызова модели (не валидации ответа)
     */
    public function classify(Ticket $ticket): AiDecisionDto
    {
        // Невалидный ответ модели сбоем не считается — он приходит в DTO как validationErrors
        // и даёт статус FAILED. Сбой самого вызова модели — ClassifierException (пробрасываем).
        $classification = $this->classifier->classify($ticket, $this->schema);
        $policy = $this->policy->checkPolicy($classification);

        $status = $classification->validationErrors === null
            ? ClassificationStatus::COMPLETED
            : ClassificationStatus::FAILED;

        return new AiDecisionDto($ticket->id, $status, $classification, $policy);
    }
}
