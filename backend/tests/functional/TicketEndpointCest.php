<?php

namespace tests\functional;

use FunctionalTester;
use Yii;

/**
 * Функциональный тест HTTP-эндпоинта `POST ticket/add`: маршрут → JSON-парсер → контроллер →
 * TicketProcessingService → JSON-ответ. Через Yii2+REST (эмуляция запроса, без реального сервера).
 */
class TicketEndpointCest
{
    public function _before(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
    }

    public function happyPathReturns201WithStableJson(FunctionalTester $I): void
    {
        $I->sendPost($this->url(), [
            'external_id' => 'FUNC-' . uniqid(),
            'tenant_id' => 'acme',
            'user_id' => 'u-func',
            'subject' => 'Не приходит письмо',
            'body' => 'Не приходит письмо для сброса пароля.',
            'source' => 'email',
        ]);

        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'classification_skipped' => false,
            'decision' => [
                'status' => 'completed',
                'model' => 'fake-classifier',
                'prompt_version' => 'prompt.v1',
            ],
        ]);

        // стабильный набор и порядок полей decision
        $decision = json_decode($I->grabResponse(), true)['decision'];
        $I->assertSame(
            [
                'id', 'status', 'category', 'priority', 'risk', 'confidence',
                'policy_decision', 'final_routing_decision', 'executable_actions_allowed',
                'matched_rules', 'model', 'schema_version', 'policy_version', 'prompt_version', 'trace_id',
            ],
            array_keys($decision),
        );
    }

    public function missingFieldsReturns422(FunctionalTester $I): void
    {
        $I->sendPost($this->url(), ['external_id' => 'FUNC-BAD-' . uniqid()]);

        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['error' => 'invalid_ticket']);
    }

    public function malformedJsonReturns400(FunctionalTester $I): void
    {
        $I->sendPost($this->url(), '{ broken');

        $I->seeResponseCodeIs(400);
    }

    private function url(): string
    {
        return Yii::$app->urlManager->createUrl(['ticket/add']);
    }
}
