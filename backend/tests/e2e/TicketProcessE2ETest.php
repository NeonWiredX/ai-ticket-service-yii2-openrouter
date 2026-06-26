<?php

namespace tests\e2e;

use app\commands\TicketController;
use app\models\Entity\AiDecision;
use app\services\Classifiers\FakeClassifier;
use app\services\Policy\PolicyV1Service;
use app\services\Prompt\ClassificationPromptV1;
use app\services\Schema\ClassificationSchemaV1;
use app\services\TicketClassificationService;
use app\services\TicketIngestionService;
use app\services\TicketProcessingService;
use Yii;
use yii\console\ExitCode;

/**
 * E2E `ticket/process`: команда прогоняет весь живой стек — приём (upsert) → классификация (Fake)
 * → персист решения в БД. Нужен postgres (orm). Проверяем код выхода, форму JSON и реальную запись.
 */
class TicketProcessE2ETest extends \Codeception\Test\Unit
{
    public function testProcessesNewTicketEndToEnd(): void
    {
        $path = $this->tempJson([
            'external_id' => 'E2E-' . uniqid(),
            'tenant_id' => 'acme',
            'user_id' => 'u-e2e',
            'subject' => 'Не приходит чек',
            'body' => 'После оплаты не пришёл чек на email.',
            'source' => 'email',
        ]);
        $controller = $this->controller();

        $code = $controller->actionProcess($path);
        @unlink($path);

        $this->assertSame(ExitCode::OK, $code);

        $out = json_decode($controller->out, true);
        $this->assertIsArray($out);
        $this->assertFalse($out['classification_skipped']);
        $this->assertSame('completed', $out['decision']['status']);
        $this->assertSame('fake-classifier', $out['decision']['model']);
        $this->assertSame('prompt.v1', $out['decision']['prompt_version']);

        // решение реально сохранено и связано с тикетом
        $saved = AiDecision::findOne($out['decision']['id']);
        $this->assertNotNull($saved);
        $this->assertSame($out['ticket_id'], $saved->ticket_id);
    }

    public function testMissingFieldsExitDataErr(): void
    {
        $path = $this->tempJson(['external_id' => 'E2E-BAD-' . uniqid()]);
        $controller = $this->controller();

        $code = $controller->actionProcess($path);
        @unlink($path);

        $this->assertSame(ExitCode::DATAERR, $code);
    }

    /** Контроллер с реальным процессингом (Fake-классификатор) и перехватом stdout. */
    private function controller(): TicketController
    {
        $processing = new TicketProcessingService(
            new TicketIngestionService(),
            new TicketClassificationService(
                new FakeClassifier(),
                new PolicyV1Service(),
                new ClassificationSchemaV1(),
                new ClassificationPromptV1(),
            ),
        );

        return new class('ticket', Yii::$app, $processing) extends TicketController {
            public string $out = '';

            public function stdout($string)
            {
                $this->out .= $string;

                return \strlen($string);
            }

            public function stderr($string)
            {
                return \strlen($string);
            }
        };
    }

    /**
     * @param array<string,mixed> $data
     */
    private function tempJson(array $data): string
    {
        $path = sys_get_temp_dir() . '/e2e-' . uniqid() . '.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }
}
