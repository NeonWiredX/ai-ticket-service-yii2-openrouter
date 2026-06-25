<?php

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use app\services\Classifiers\FakeClassifier;
use app\services\Classifiers\OpenRouter\OpenRouterClassifier;
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
 * (TicketProcessingService, OpenRouterClient и т.д.) контейнер собирает автовайрингом по type-hint'ам.
 * Сервисы stateless — singletons.
 *
 * Классификатор выбирается по env: есть OPENROUTER_API_KEY → OpenRouter, иначе Fake
 * (демо-сидер / CI / локалка без ключа продолжают работать на FakeClassifier).
 */
return [
    'singletons' => [
        // транспорт OpenRouter
        HttpClientInterface::class => static fn (): HttpClientInterface => HttpClient::create(),
        OpenRouterConfig::class => static fn (): OpenRouterConfig => OpenRouterConfig::fromParams(
            Yii::$app->params['openrouter'] ?? []
        ),

        TicketClassifierInterface::class => static fn ($container): TicketClassifierInterface =>
            (string) (Yii::$app->params['openrouter']['apiKey'] ?? '') !== ''
                ? $container->get(OpenRouterClassifier::class)
                : new FakeClassifier(),

        TicketPolicyInterface::class => PolicyV1Service::class,
        ClassificationSchemaInterface::class => ClassificationSchemaV1::class,
        TicketPromptInterface::class => ClassificationPromptV1::class,
    ],
];
