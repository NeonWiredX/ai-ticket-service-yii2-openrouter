<?php

namespace app\commands;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\services\Classifiers\FakeClassifier;
use app\services\Exceptions\AiDecisionSaveException;
use app\services\Exceptions\TicketSaveException;
use app\services\Exceptions\TicketValidationException;
use app\services\Policy\PolicyV1Service;
use app\services\Schema\ClassificationSchemaV1;
use app\services\TicketClassificationService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Наполнение БД тестовыми тикетами (для разработки): генерит случайный вход, создаёт Ticket,
 * прогоняет через TicketClassificationService (фейковый классификатор + политика v1) и «в лоб»
 * сохраняет полученный AiDecisionDto как AiDecision.
 * Persist здесь намеренно примитивный — позже приём уедет в ингестор, запись решения — в репозиторий.
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
     * Создаёт тикет(ы) со случайными данными через сервис классификации.
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

        $service = new TicketClassificationService(new FakeClassifier(), new PolicyV1Service(), new ClassificationSchemaV1());

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $ticket = new Ticket();
                if (!$ticket->load($this->randomTicketInput(), '') || !$ticket->validate()) {
                    throw new TicketValidationException(
                        json_encode($ticket->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    );
                }
                if (!$ticket->save(false)) {
                    throw new TicketSaveException();
                }

                // домен возвращает DTO (БД не трогает)
                $decision = $service->classify($ticket);

                // «тупая» персистентность решения (позже — AiDecisionRepository)
                $ai = new AiDecision();
                $ai->load($decision->toAiDecisionAttributes(), '');
                if (!$ai->save()) {
                    throw new AiDecisionSaveException(
                        json_encode($ai->getErrors(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    );
                }
            } catch (\Throwable $e) {
                $this->stderr('✘ ' . $e::class . ': ' . $e->getMessage() . "\n", Console::FG_RED);
                continue;
            }

            $created++;
            $this->stdout(
                "✔ ticket #{$ai->ticket_id} → ai_decision #{$ai->id} "
                . "{$ai->status}/{$ai->category} {$ai->policy_decision} → {$ai->final_routing_decision}\n",
                Console::FG_GREEN
            );
        }

        $this->stdout("Создано: {$created}/{$count}\n");

        return $created === $count ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * @return array<string,string> случайный валидный вход тикета
     */
    private function randomTicketInput(): array
    {
        return [
            'external_id' => 'EXT-' . strtoupper(bin2hex(random_bytes(6))),
            'tenant_id' => $this->pick(self::TENANTS),
            'user_id' => 'user-' . random_int(1000, 9999),
            'subject' => $this->pick(self::SUBJECTS),
            'body' => $this->randomBody(),
            'source' => $this->pick(self::SOURCES),
        ];
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
