<?php

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use app\services\Classifiers\FakeClassifier;
use app\services\Classifiers\OpenRouter\OpenRouterConfig;
use app\services\Classifiers\TicketClassifierInterface;
use app\services\Policy\PolicyV1Service;
use app\services\Policy\TicketPolicyInterface;
use app\services\Prompt\ClassificationPromptV1;
use app\services\Prompt\TicketPromptInterface;
use app\services\Schema\ClassificationSchemaInterface;
use app\services\Schema\ClassificationSchemaV1;

/**
 * Привязки DI-контейнера (composition root). Интерфейсы → реализации; остальной граф
 * контейнер собирает автовайрингом по type-hint'ам. Сервисы stateless — singletons.
 *
 * Классификатор привязан плоско. Для OpenRouter заменить на
 * app\services\Classifiers\OpenRouter\OpenRouterClassifier::class (его deps определены ниже).
 */
return [
    'singletons' => [
        HttpClientInterface::class => static fn (): HttpClientInterface => HttpClient::create(),
        OpenRouterConfig::class => static fn (): OpenRouterConfig => OpenRouterConfig::fromParams(
            Yii::$app->params['openrouter'] ?? []
        ),

        TicketClassifierInterface::class => FakeClassifier::class,
        TicketPolicyInterface::class => PolicyV1Service::class,
        ClassificationSchemaInterface::class => ClassificationSchemaV1::class,
        TicketPromptInterface::class => ClassificationPromptV1::class,
    ],
];
