<?php

namespace tests\unit\services;

use app\services\Policy\PolicyV1Service;
use app\services\Schema\ClassificationSchemaV1;

/**
 * Контракт «сырой ответ модели → вердикт политики» на фикстурах (tests/_data/policy_fixtures.json).
 * Детерминированно, без модели/сети/БД: фикстура → ClassificationSchemaV1::parse() → PolicyV1Service.
 *
 * Группы:
 *  - policy_boundary — кейсы у границ политики (порог confidence 0.59/0.60, risky-категория поверх
 *    высокой уверенности, money_movement / privacy / security / destructive_action);
 *  - model_failure   — невалидный ответ модели (битый enum / confidence вне диапазона / нет поля) →
 *    validationErrors → blocked → manual_triage.
 *
 * Тут проверяется именно policy layer (ценность бэкенда), а не только категория.
 */
class PolicyDecisionTest extends \Codeception\Test\Unit
{
    /**
     * @dataProvider policyFixtures
     * @param array<string,mixed> $case
     */
    public function testModelOutputMapsToPolicyDecision(array $case): void
    {
        $dto = (new ClassificationSchemaV1())->parse(
            $case['model_output'],
            model: 'fixture',
            promptVersion: 'prompt.v1',
            traceId: 'fixture',
        );
        $policy = (new PolicyV1Service())->checkPolicy($dto);
        $attrs = $policy->toAiDecisionAttributes();

        $name = $case['name'];
        $expect = $case['expect'];

        $this->assertSame($expect['policy_decision'], $policy->decision->value, "{$name}: policy_decision");
        $this->assertSame($expect['final_routing_decision'], $policy->finalRoutingDecision->value, "{$name}: final_routing");
        $this->assertSame($expect['executable_actions_allowed'], $attrs['executable_actions_allowed'], "{$name}: executable_actions_allowed");

        if (isset($expect['matched_rules'])) {
            $this->assertSame($expect['matched_rules'], $policy->matchedRules, "{$name}: matched_rules");
        }
    }

    /**
     * @return array<string, array{0: array<string,mixed>}>
     */
    public static function policyFixtures(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__ . '/../../_data/policy_fixtures.json'), true);

        $out = [];
        foreach ($cases as $case) {
            $out[$case['name']] = [$case];
        }

        return $out;
    }
}
