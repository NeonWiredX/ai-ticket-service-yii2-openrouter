<?php

namespace app\services\Schema;

use app\services\Dto\ClassificationResultDto;

/**
 * Версионированный контракт ответа классификатора (граница).
 * Один источник правды: {@see self::getSchema()} уходит модели (structured output),
 * {@see self::parse()} тем же контрактом валидирует ответ и собирает DTO.
 */
interface ClassificationSchemaInterface
{
    public function getVersion(): string;

    /**
     * JSON Schema ожидаемого ответа модели (для tool input_schema / structured output).
     *
     * @return array<string,mixed>
     */
    public function getSchema(): array;

    public function getJson(): string;

    /**
     * Валидирует сырой ответ модели по контракту и собирает DTO
     * (с validationErrors при несоответствии — без исключений).
     *
     * @param array<string,mixed> $rawOutput
     */
    public function parse(
        array $rawOutput,
        string $model,
        string $traceId,
        ?int $latencyMs = null,
        int $retryCount = 0,
    ): ClassificationResultDto;
}
