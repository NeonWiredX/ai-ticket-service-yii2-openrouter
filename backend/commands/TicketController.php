<?php

namespace app\commands;

use app\models\Entity\Tickets;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Создание тикетов со случайными данными (для разработки/наполнения БД).
 */
class TicketController extends Controller
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
     * Создаёт тикет(ы) со случайными значениями согласно схеме.
     *
     * Примеры:
     *   yii ticket/add        — один тикет
     *   yii ticket/add 10     — десять тикетов
     *
     * @param int $count сколько тикетов создать
     */
    public function actionAdd(int $count = 1): int
    {
        if ($count < 1) {
            $this->stderr("count должен быть >= 1\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $ticket = new Tickets([
                'external_id' => 'EXT-' . strtoupper(bin2hex(random_bytes(6))),
                'tenant_id' => $this->pick(self::TENANTS),
                'user_id' => 'user-' . random_int(1000, 9999),
                'subject' => $this->pick(self::SUBJECTS),
                'body' => $this->randomBody(),
                'source' => $this->pick(self::SOURCES),
            ]);

            if ($ticket->save()) {
                $created++;
                $this->stdout(
                    "✔ #{$ticket->id} {$ticket->external_id} [{$ticket->tenant_id}/{$ticket->source}] {$ticket->subject}\n",
                    Console::FG_GREEN
                );
            } else {
                $this->stderr(
                    '✘ ошибка: ' . json_encode($ticket->getErrors(), JSON_UNESCAPED_UNICODE) . "\n",
                    Console::FG_RED
                );
            }
        }

        $this->stdout("Создано: {$created}/{$count}\n");

        return $created === $count ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
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
