<?php

namespace tests\unit\commands;

use app\commands\TicketController;
use app\models\Entity\AiDecision;
use app\models\Entity\Ticket;
use app\services\Dto\IngestTicketCommand;
use app\services\Dto\TicketProcessingResult;
use app\services\Exceptions\ClassifierException;
use app\services\Exceptions\TicketValidationException;
use app\services\TicketProcessingService;
use Yii;
use yii\console\ExitCode;

/**
 * Контракт CLI `ticket/process`: коды выхода и стабильная форма JSON.
 * Процессинг подменён (мок), тикет/решение — in-memory AR → без сети и БД.
 */
class TicketControllerTest extends \Codeception\Test\Unit
{
    public function testMissingFileReturnsNoInput(): void
    {
        $controller = $this->controller($this->processingReturning($this->cannedResult()));

        $code = $controller->actionProcess('/no/such/file-' . uniqid() . '.json');

        $this->assertSame(ExitCode::NOINPUT, $code);
        $this->assertSame('', $controller->out, 'на ошибке stdout пуст');
    }

    public function testInvalidJsonReturnsDataErr(): void
    {
        $path = $this->tempJson('{ битый json');
        $controller = $this->controller($this->processingReturning($this->cannedResult()));

        $code = $controller->actionProcess($path);
        @unlink($path);

        $this->assertSame(ExitCode::DATAERR, $code);
    }

    public function testValidJsonPrintsStableJson(): void
    {
        $path = $this->tempJson('{"external_id":"E1","tenant_id":"acme","user_id":"u","subject":"s","body":"b","source":"email"}');
        $controller = $this->controller($this->processingReturning($this->cannedResult()));

        $code = $controller->actionProcess($path);
        @unlink($path);

        $this->assertSame(ExitCode::OK, $code);

        $out = json_decode($controller->out, true);
        $this->assertIsArray($out);
        $this->assertSame(['ticket_id', 'classification_skipped', 'decision'], array_keys($out));
        $this->assertSame(7, $out['ticket_id']);
        $this->assertFalse($out['classification_skipped']);
        $this->assertSame(
            [
                'id', 'status', 'category', 'priority', 'risk', 'confidence',
                'policy_decision', 'final_routing_decision', 'executable_actions_allowed',
                'matched_rules', 'model', 'schema_version', 'policy_version', 'prompt_version', 'trace_id',
            ],
            array_keys($out['decision']),
            'стабильный набор и порядок полей decision',
        );
        $this->assertSame('completed', $out['decision']['status']);
        $this->assertSame('billing', $out['decision']['category']);
        $this->assertSame(0.87, $out['decision']['confidence']);
        $this->assertTrue($out['decision']['executable_actions_allowed']);
        $this->assertSame(['auto_allowed'], $out['decision']['matched_rules']);
        $this->assertSame('prompt.v1', $out['decision']['prompt_version']);
    }

    public function testMissingFieldsReturnDataErr(): void
    {
        // приёмник кидает TicketValidationException (нет обязательных полей) → DATAERR, не UNSPECIFIED_ERROR
        $path = $this->tempJson('{"external_id":""}');
        $controller = $this->controller(
            $this->processingThrowing(new TicketValidationException('{"tenant_id":["required"]}'))
        );

        $code = $controller->actionProcess($path);
        @unlink($path);

        $this->assertSame(ExitCode::DATAERR, $code);
    }

    public function testRuntimeFailureReturnsUnspecifiedError(): void
    {
        // сбой модели / раннтайма → UNSPECIFIED_ERROR
        $path = $this->tempJson('{"external_id":"E1","tenant_id":"acme","user_id":"u","subject":"s","body":"b","source":"email"}');
        $controller = $this->controller($this->processingThrowing(new ClassifierException('model down')));

        $code = $controller->actionProcess($path);
        @unlink($path);

        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $code);
    }

    /** Контроллер с перехватом stdout/stderr (вместо записи в реальные потоки). */
    private function controller(TicketProcessingService $processing): TicketController
    {
        return new class('ticket', Yii::$app, $processing) extends TicketController {
            public string $out = '';
            public string $err = '';

            public function stdout($string)
            {
                $this->out .= $string;

                return \strlen($string);
            }

            public function stderr($string)
            {
                $this->err .= $string;

                return \strlen($string);
            }
        };
    }

    private function processingReturning(TicketProcessingResult $result): TicketProcessingService
    {
        return new class($result) extends TicketProcessingService {
            public function __construct(private TicketProcessingResult $result)
            {
            }

            public function process(IngestTicketCommand $command): TicketProcessingResult
            {
                return $this->result;
            }
        };
    }

    private function processingThrowing(\Throwable $e): TicketProcessingService
    {
        return new class($e) extends TicketProcessingService {
            public function __construct(private \Throwable $e)
            {
            }

            public function process(IngestTicketCommand $command): TicketProcessingResult
            {
                throw $this->e;
            }
        };
    }

    private function tempJson(string $content): string
    {
        $path = sys_get_temp_dir() . '/ticket-' . uniqid() . '.json';
        file_put_contents($path, $content);

        return $path;
    }

    private function cannedResult(): TicketProcessingResult
    {
        $ticket = new class extends Ticket {
            public function attributes(): array
            {
                return ['id', 'external_id', 'tenant_id', 'user_id', 'subject', 'body', 'source', 'created_at'];
            }
        };
        $ticket->id = 7;

        $decision = new class extends AiDecision {
            public function attributes(): array
            {
                return [
                    'id', 'ticket_id', 'schema_version', 'policy_version', 'prompt_version', 'model', 'status',
                    'category', 'priority', 'risk', 'confidence', 'summary', 'reason', 'model_routing_decision',
                    'final_routing_decision', 'policy_decision', 'executable_actions_allowed', 'matched_rules',
                    'validation_errors', 'raw_model_output', 'retry_count', 'latency_ms', 'trace_id', 'created_at',
                ];
            }
        };
        $decision->id = 7;
        $decision->status = 'completed';
        $decision->category = 'billing';
        $decision->priority = 'low';
        $decision->risk = 'none';
        $decision->confidence = 0.87;
        $decision->policy_decision = 'allowed';
        $decision->final_routing_decision = 'support_queue';
        $decision->executable_actions_allowed = true;
        $decision->matched_rules = ['auto_allowed'];
        $decision->model = 'fake-classifier';
        $decision->schema_version = 'classification.v1';
        $decision->policy_version = 'policy.v1';
        $decision->prompt_version = 'prompt.v1';
        $decision->trace_id = 'trace-x';

        return new TicketProcessingResult($ticket, $decision, classificationSkipped: false);
    }
}
