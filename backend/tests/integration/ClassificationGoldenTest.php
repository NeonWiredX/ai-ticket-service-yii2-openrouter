<?php

namespace tests\integration;

use app\models\Entity\Ticket;
use app\services\Classifiers\OpenRouter\OpenRouterClassifier;
use app\services\Classifiers\OpenRouter\OpenRouterClient;
use app\services\Classifiers\OpenRouter\OpenRouterConfig;
use app\services\Dto\ClassificationResultDto;
use app\services\Policy\PolicyV1Service;
use app\services\Prompt\ClassificationPromptV1;
use app\services\Schema\ClassificationSchemaV1;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Golden-eval против реальной модели OpenRouter: набор эталонных тикетов с ожидаемыми
 * category / risk / policy, плюс регрессии на prompt injection и неоднозначные кейсы.
 *
 * Набор — tests/_data/golden_tickets.json. Запускается только при OPENROUTER_API_KEY (сеть, N вызовов!),
 * иначе скип. БД не нужна. Запуск точечно: `codecept run integration ClassificationGoldenTest`
 * или по группе: `codecept run integration -g golden` / исключить `--skip-group golden`.
 *
 * @group golden
 */
class ClassificationGoldenTest extends \Codeception\Test\Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        if ((getenv('OPENROUTER_API_KEY') ?: '') === '') {
            $this->markTestSkipped('OPENROUTER_API_KEY не задан — golden-eval пропущен.');
        }
    }

    /**
     * @dataProvider goldenCases
     * @param array<string,mixed> $case
     */
    public function testGoldenCase(array $case): void
    {
        $dto = $this->classify($case['ticket']['subject'] ?? '', $case['ticket']['body'] ?? '');
        $policy = (new PolicyV1Service())->checkPolicy($dto);

        $name = $case['name'];
        $cat = $dto->category?->value;
        $risk = $dto->risk?->value;
        $pol = $policy->decision->value;
        $ctx = sprintf(
            ' [%s] category=%s priority=%s risk=%s confidence=%s policy=%s',
            $name, $cat, $dto->priority?->value, $risk, $dto->confidence, $pol
        );

        // структурно валидный ответ — всегда
        $this->assertNull($dto->validationErrors, "невалидный structured output{$ctx}");

        $e = $case['expect'] ?? [];
        if (isset($e['category'])) {
            $this->assertSame($e['category'], $cat, "category{$ctx}");
        }
        if (isset($e['category_in'])) {
            $this->assertContains($cat, $e['category_in'], "category{$ctx}");
        }
        if (isset($e['category_not'])) {
            $this->assertNotSame($e['category_not'], $cat, "category{$ctx}");
        }
        if (isset($e['risk'])) {
            $this->assertSame($e['risk'], $risk, "risk{$ctx}");
        }
        if (isset($e['risk_in'])) {
            $this->assertContains($risk, $e['risk_in'], "risk{$ctx}");
        }
        if (isset($e['risk_not'])) {
            $this->assertNotSame($e['risk_not'], $risk, "risk{$ctx}");
        }
        if (isset($e['policy_decision'])) {
            $this->assertSame($e['policy_decision'], $pol, "policy{$ctx}");
        }
        if (isset($e['policy_decision_in'])) {
            $this->assertContains($pol, $e['policy_decision_in'], "policy{$ctx}");
        }
    }

    /**
     * @return array<string, array{0: array<string,mixed>}>
     */
    public static function goldenCases(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__ . '/../_data/golden_tickets.json'), true);

        $out = [];
        foreach ($cases as $case) {
            $out[$case['name']] = [$case];
        }

        return $out;
    }

    private function classify(string $subject, string $body): ClassificationResultDto
    {
        $config = new OpenRouterConfig(
            apiKey: getenv('OPENROUTER_API_KEY'),
            model: getenv('OPENROUTER_MODEL') ?: 'anthropic/claude-sonnet-4.5',
        );
        $classifier = new OpenRouterClassifier(new OpenRouterClient(HttpClient::create(), $config), $config);

        $ticket = new class extends Ticket {
            public function attributes(): array
            {
                return ['id', 'external_id', 'tenant_id', 'user_id', 'subject', 'body', 'source', 'created_at'];
            }
        };
        $ticket->subject = $subject;
        $ticket->body = $body;

        return $classifier->classify($ticket, new ClassificationSchemaV1(), new ClassificationPromptV1());
    }
}
