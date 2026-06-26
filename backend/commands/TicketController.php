<?php

namespace app\commands;

use app\services\Dto\IngestTicketCommand;
use app\services\Dto\TicketProcessingResult;
use app\services\TicketProcessingService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Обработка одного тикета из JSON-файла: `yii ticket/process path/to/ticket.json`.
 * Читает JSON → IngestTicketCommand → TicketProcessingService → печатает стабильный JSON в stdout.
 * Ошибки идут в stderr + ненулевой код выхода; stdout остаётся каналом результата.
 */
class TicketController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly TicketProcessingService $processing,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Обработать тикет из JSON-файла и напечатать результат как JSON.
     *
     * @param string $path путь к JSON-файлу тикета
     */
    public function actionProcess(string $path): int
    {
        if (!is_file($path) || !is_readable($path)) {
            $this->stderr("Файл не найден или недоступен: {$path}\n", Console::FG_RED);
            return ExitCode::NOINPUT;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            $this->stderr("Некорректный JSON в файле: {$path}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        try {
            $result = $this->processing->process(IngestTicketCommand::fromArray($data));
        } catch (\Throwable $e) {
            $this->stderr('✘ ' . $e::class . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(json_encode(
            $this->resultToArray($result),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n");

        return ExitCode::OK;
    }

    /**
     * Стабильный результат: фиксированный набор и порядок полей (для машинного потребления).
     *
     * @return array<string,mixed>
     */
    private function resultToArray(TicketProcessingResult $result): array
    {
        $decision = $result->decision;

        return [
            'ticket_id' => (int) $result->ticket->id,
            'classification_skipped' => $result->classificationSkipped,
            'decision' => [
                'id' => (int) $decision->id,
                'status' => $decision->status,
                'category' => $decision->category,
                'priority' => $decision->priority,
                'risk' => $decision->risk,
                'confidence' => $decision->confidence === null ? null : (float) $decision->confidence,
                'policy_decision' => $decision->policy_decision,
                'final_routing_decision' => $decision->final_routing_decision,
                'executable_actions_allowed' => (bool) $decision->executable_actions_allowed,
                'matched_rules' => $decision->matched_rules,
                'model' => $decision->model,
                'schema_version' => $decision->schema_version,
                'policy_version' => $decision->policy_version,
                'prompt_version' => $decision->prompt_version,
                'trace_id' => $decision->trace_id,
            ],
        ];
    }
}
