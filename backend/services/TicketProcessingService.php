<?php

namespace app\services;

use app\models\Entity\AiDecision;
use app\services\Dto\IngestTicketCommand;
use app\services\Dto\TicketProcessingResult;
use app\services\Exceptions\AiDecisionSaveException;

/**
 * Оркестрация обработки тикета: приём → (если решения ещё нет) классификация → сохранение решения.
 * Связывает существующие сервисы; идемпотентность сабмита (повтор не классифицируется заново) — здесь.
 * Сохранение решения пока «в лоб» (new AiDecision + save) — позже уедет в AiDecisionRepository.
 */
class TicketProcessingService
{
    public function __construct(
        protected TicketIngestionService      $ingestion,
        protected TicketClassificationService $classification,
    ) {
    }

    /**
     * @throws AiDecisionSaveException если решение не удалось сохранить
     */
    public function process(IngestTicketCommand $command): TicketProcessingResult
    {
        $result = $this->ingestion->ingest($command);
        $ticket = $result->ticket;

        // идемпотентность: если решение по тикету уже есть — заново не классифицируем.
        // Новый тикет решений иметь не может — лишний запрос не делаем.
        $existing = $result->wasCreated
            ? null
            : AiDecision::find()->where(['ticket_id' => $ticket->id])->orderBy(['id' => SORT_DESC])->one();
        if ($existing !== null) {
            return new TicketProcessingResult($ticket, $existing, classificationSkipped: true);
        }

        $decisionDto = $this->classification->classify($ticket);

        // «тупая» персистентность решения (позже — AiDecisionRepository)
        $aiDecision = new AiDecision();
        $aiDecision->load($decisionDto->toAiDecisionAttributes(), '');
        if (!$aiDecision->save()) {
            throw new AiDecisionSaveException(
                json_encode($aiDecision->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }

        return new TicketProcessingResult($ticket, $aiDecision, classificationSkipped: false);
    }
}
