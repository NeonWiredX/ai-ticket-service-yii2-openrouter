<?php

namespace app\services\Classifiers;

use app\models\Entity\Ticket;
use app\services\Dto\ClassificationResultDto;
use app\services\Exceptions\ClassifierException;
use app\services\Prompt\TicketPromptInterface;
use app\services\Schema\ClassificationSchemaInterface;

interface TicketClassifierInterface
{
    /**
     * Невалидный ответ модели сбоем НЕ считается — он возвращается в DTO как validationErrors.
     * ClassifierException — только для сбоя самого вызова модели (сеть/таймаут/протокол).
     *
     * @param ClassificationSchemaInterface $schema контракт ответа модели (вход классификатора)
     * @param TicketPromptInterface $prompt инструкция модели (вход классификатора)
     * @throws ClassifierException при сбое вызова модели
     */
    public function classify(Ticket $ticket, ClassificationSchemaInterface $schema, TicketPromptInterface $prompt): ClassificationResultDto;
}
