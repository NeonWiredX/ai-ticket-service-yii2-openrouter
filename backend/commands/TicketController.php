<?php

namespace app\commands;

use app\services\Dto\IngestTicketCommand;
use app\services\Exceptions\TicketValidationException;
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
        } catch (TicketValidationException $e) {
            // невалидные / отсутствующие поля тикета — это проблема входных данных
            $this->stderr('✘ невалидный тикет: ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::DATAERR;
        } catch (\Throwable $e) {
            // сбой раннтайма / модели / БД
            $this->stderr('✘ ' . $e::class . ': ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n");

        return ExitCode::OK;
    }
}
