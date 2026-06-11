<?php

namespace app\commands;

use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\models\Enum\Category;
use app\models\Enum\ClassificationStatus;
use app\models\Enum\PolicyDecision;
use app\models\Enum\Priority;
use app\models\Enum\Risk;
use app\models\Enum\RoutingDecision;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Наполнение БД тестовыми тикетами и связанными AI-решениями (для разработки).
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

    private const MODELS = ['claude-opus-4-8', 'claude-sonnet-4-6', 'claude-haiku-4-5'];

    private const RULES = ['rule_pii_detected', 'rule_refund_request', 'rule_high_priority', 'rule_vip_customer', 'rule_spam'];

    /**
     * Создаёт тикет(ы) со случайными значениями и к каждому — связанное AI-решение.
     *
     * Примеры:
     *   yii test-ticket/add        — один тикет + решение
     *   yii test-ticket/add 10     — десять тикетов, к каждому решение
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
            $ticket = $this->makeTicket();
            if (!$ticket->save()) {
                $this->stderr('✘ ticket: ' . json_encode($ticket->getErrors(), JSON_UNESCAPED_UNICODE) . "\n", Console::FG_RED);
                continue;
            }

            $decision = $this->makeDecision($ticket->id);
            if (!$decision->save()) {
                $this->stderr('✘ ai_decision: ' . json_encode($decision->getErrors(), JSON_UNESCAPED_UNICODE) . "\n", Console::FG_RED);
                continue;
            }

            $created++;
            $this->stdout(
                "✔ ticket #{$ticket->id} {$ticket->external_id} [{$ticket->tenant_id}/{$ticket->source}] {$ticket->subject}\n",
                Console::FG_GREEN
            );
            $this->stdout(
                "    └ ai_decision #{$decision->id} {$decision->status}/{$decision->category} "
                . "conf={$decision->confidence} → {$decision->final_routing_decision}\n",
                Console::FG_GREY
            );
        }

        $this->stdout("Создано тикетов с решениями: {$created}/{$count}\n");

        return $created === $count ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function makeTicket(): Ticket
    {
        return new Ticket([
            'external_id' => 'EXT-' . strtoupper(bin2hex(random_bytes(6))),
            'tenant_id' => $this->pick(self::TENANTS),
            'user_id' => 'user-' . random_int(1000, 9999),
            'subject' => $this->pick(self::SUBJECTS),
            'body' => $this->randomBody(),
            'source' => $this->pick(self::SOURCES),
        ]);
    }

    private function makeDecision(int $ticketId): AiDecision
    {
        $category = $this->pickEnum(Category::class);
        $priority = $this->pickEnum(Priority::class);
        $risk = $this->pickEnum(Risk::class);
        $confidence = round(random_int(5000, 10000) / 10000, 4);

        return new AiDecision([
            'ticket_id' => $ticketId,
            'schema_version' => 'v1',
            'policy_version' => 'p1',
            'model' => $this->pick(self::MODELS),
            'status' => $this->pickEnum(ClassificationStatus::class),
            'category' => $category,
            'priority' => $priority,
            'risk' => $risk,
            'confidence' => $confidence,
            'summary' => $this->pick(self::SENTENCES),
            'reason' => $this->randomBody(),
            'model_routing_decision' => $this->pickEnum(RoutingDecision::class),
            'final_routing_decision' => $this->pickEnum(RoutingDecision::class),
            'policy_decision' => $this->pickEnum(PolicyDecision::class),
            'executable_actions_allowed' => (bool) random_int(0, 1),
            'matched_rules' => $this->randomRules(),
            'validation_errors' => null,
            'raw_model_output' => [
                'category' => $category,
                'priority' => $priority,
                'risk' => $risk,
                'confidence' => $confidence,
            ],
            'retry_count' => random_int(0, 2),
            'latency_ms' => random_int(150, 4000),
            'trace_id' => 'trace-' . bin2hex(random_bytes(8)),
        ]);
    }

    /**
     * Случайное backing-значение из строкового enum.
     *
     * @param class-string<\BackedEnum> $enum
     */
    private function pickEnum(string $enum): string
    {
        $cases = $enum::cases();

        return $cases[array_rand($cases)]->value;
    }

    /**
     * @return string[]
     */
    private function randomRules(): array
    {
        $rules = self::RULES;
        shuffle($rules);

        return array_slice($rules, 0, random_int(0, 3));
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
