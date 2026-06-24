<?php

namespace app\services\Dto;

/**
 * Типизированный вход приёма тикета (граница приложения), иммутабельный.
 * Транспорт (консоль/HTTP) строит его из своего ввода; в атрибуты Ticket — через toTicketAttributes().
 * Сам не валидирует — формат/длины проверяет приёмник по правилам Ticket.
 */
final class IngestTicketCommand
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $tenantId,
        public readonly string $userId,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $source,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function toTicketAttributes(): array
    {
        return [
            'external_id' => $this->externalId,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'subject' => $this->subject,
            'body' => $this->body,
            'source' => $this->source,
        ];
    }
}
