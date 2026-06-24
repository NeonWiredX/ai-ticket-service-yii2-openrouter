<?php

namespace app\commands;

use app\services\Classifiers\FakeClassifier;
use app\services\Dto\IngestTicketCommand;
use app\services\Policy\PolicyV1Service;
use app\services\Schema\ClassificationSchemaV1;
use app\services\TicketClassificationService;
use app\services\TicketIngestionService;
use app\services\TicketProcessingService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Наполнение БД тестовыми тикетами (для разработки): генерит случайный вход и прогоняет его
 * через TicketProcessingService (приём → классификация → сохранение решения).
 * Контроллер — только composition root и вывод; вся оркестрация в сервисе.
 */
class TestTicketController extends Controller
{
    private const SOURCES = ['email', 'web_form', 'chat', 'phone', 'api', 'telegram'];

    private const TENANTS = ['acme', 'globex', 'initech', 'umbrella', 'soylent'];

    private const SUBJECTS = [
        'Не приходит письмо для сброса пароля',
        'Ошибка оплаты при оформлении заказа',
        'Как изменить тарифный план?',
        'Не работает выгрузка отчёта в PDF',
        'Двойное списание средств с карты',
        'Приложение вылетает при входе',
        'Запрос на возврат денег',
        'Не могу подключить интеграцию с CRM',
        'Медленно загружается личный кабинет',
        'Где посмотреть историю заказов?',
    ];

    private const SENTENCES = [
        'Проблема воспроизводится стабильно на последней версии.',
        'Пробовал перезайти и очистить кэш — не помогло.',
        'Ошибка появилась сегодня утром, раньше всё работало.',
        'Прикладываю скриншот с текстом ошибки.',
        'Очень прошу разобраться как можно скорее, блокирует работу.',
        'У коллег с такими же настройками всё в порядке.',
        'Готов предоставить доступ для диагностики.',
        'Деньги списались, но заказ не оформился.',
        'Использую браузер Chrome последней версии.',
        'Пишу повторно, на предыдущее обращение ответа не было.',
    ];

    /**
     * Создаёт тикет(ы) со случайными данными через TicketProcessingService.
     *
     * Примеры:
     *   yii test-ticket/add        — один тикет + решение
     *   yii test-ticket/add 10     — десять
     *
     * @param int $count сколько тикетов создать
     */
    public function actionAdd(int $count = 1): int
    {
        if ($count < 1) {
            $this->stderr("count должен быть >= 1\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $processing = new TicketProcessingService(
            new TicketIngestionService(),
            new TicketClassificationService(new FakeClassifier(), new PolicyV1Service(), new ClassificationSchemaV1()),
        );

        $created = 0;
        $skipped = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $result = $processing->process($this->randomCommand());
            } catch (\Throwable $e) {
                $this->stderr('✘ ' . $e::class . ': ' . $e->getMessage() . "\n", Console::FG_RED);
                continue;
            }

            $ai = $result->decision;
            if ($result->skipped) {
                $skipped++;
                $this->stdout("• ticket #{$ai->ticket_id} уже классифицирован (ai_decision #{$ai->id}) — пропуск\n");
                continue;
            }

            $created++;
            $this->stdout(
                "✔ ticket #{$ai->ticket_id} → ai_decision #{$ai->id} "
                . "{$ai->status}/{$ai->category} {$ai->policy_decision} → {$ai->final_routing_decision}\n",
                Console::FG_GREEN
            );
        }

        $this->stdout("Создано: {$created}/{$count}" . ($skipped > 0 ? ", пропущено: {$skipped}" : '') . "\n");

        return $created + $skipped === $count ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /** Случайная валидная команда приёма. */
    private function randomCommand(): IngestTicketCommand
    {
        return new IngestTicketCommand(
            externalId: 'EXT-' . strtoupper(bin2hex(random_bytes(6))),
            tenantId: $this->pick(self::TENANTS),
            userId: 'user-' . random_int(1000, 9999),
            subject: $this->pick(self::SUBJECTS),
            body: $this->randomBody(),
            source: $this->pick(self::SOURCES),
        );
    }

    /**
     * @param string[] $items
     */
    private function pick(array $items): string
    {
        return $items[array_rand($items)];
    }

    private function randomBody(): string
    {
        $sentences = self::SENTENCES;
        shuffle($sentences);

        return implode(' ', array_slice($sentences, 0, random_int(2, 4)));
    }
}
