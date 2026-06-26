<?php

namespace app\services\Dto;

/**
 * Типизированный вход приёма тикета (граница приложения), иммутабельный.
 * Из недоверенного массива (JSON-payload) собирается через {@see self::fromArray()};
 * в атрибуты Ticket — через {@see self::toTicketAttributes()}.
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
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? ''),
            tenantId: (string) ($data['tenant_id'] ?? ''),
            userId: (string) ($data['user_id'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            source: (string) ($data['source'] ?? ''),
        );
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
