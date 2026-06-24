<?php

namespace app\services;

use app\models\Entity\Ticket;
use app\services\Dto\IngestResult;
use app\services\Dto\IngestTicketCommand;
use app\services\Exceptions\TicketValidationException;
use Yii;

/**
 * Приём тикета: валидирует вход и идемпотентно сохраняет Ticket.
 * Дубль по (tenant_id, external_id) возвращается как есть — без создания и без ошибки.
 * Атомарность дедупа обеспечивает индекс ux_tickets_tenant_external через
 * INSERT ... ON CONFLICT DO NOTHING. Классификацией не занимается.
 */
class TicketIngestionService
{
    /**
     * @throws TicketValidationException если вход не проходит валидацию
     */
    public function ingest(IngestTicketCommand $command): IngestResult
    {
        $attributes = $command->toTicketAttributes();

        $ticket = new Ticket();
        $ticket->setAttributes($attributes, false);
        if (!$ticket->validate()) {
            throw new TicketValidationException(
                json_encode($ticket->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }

        // INSERT ... ON CONFLICT DO NOTHING: 1 — вставили, 0 — такой тикет уже есть.
        $inserted = Yii::$app->db->createCommand()
            ->upsert(Ticket::tableName(), $attributes, false)
            ->execute();

        $ticket = Ticket::findOne([
            'tenant_id' => $command->tenantId,
            'external_id' => $command->externalId,
        ]);

        return new IngestResult($ticket, wasCreated: $inserted === 1);
    }
}
