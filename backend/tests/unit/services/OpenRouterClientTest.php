<?php

namespace tests\unit\services;

use app\services\Classifiers\OpenRouter\OpenRouterClient;
use app\services\Classifiers\OpenRouter\OpenRouterConfig;
use app\services\Exceptions\ClassifierException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Транспорт OpenRouter тестируется через MockHttpClient — без сети.
 */
class OpenRouterClientTest extends \Codeception\Test\Unit
{
    private function client(MockHttpClient $http): OpenRouterClient
    {
        return new OpenRouterClient($http, new OpenRouterConfig(apiKey: 'test-key', model: 'm'));
    }

    public function testReturnsDecodedResponse(): void
    {
        $http = new MockHttpClient(new MockResponse('{"ok":true,"n":1}', ['http_code' => 200]));

        $this->assertSame(['ok' => true, 'n' => 1], $this->client($http)->chatCompletion(['x' => 1]));
    }

    public function testSendsAuthAndPayloadToChatCompletions(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = compact('method', 'url', 'options');

            return new MockResponse('{}', ['http_code' => 200]);
        });

        $this->client($http)->chatCompletion(['model' => 'm', 'messages' => []]);

        $this->assertSame('POST', $captured['method']);
        $this->assertStringEndsWith('/chat/completions', $captured['url']);
        $this->assertContains('Authorization: Bearer test-key', $captured['options']['headers']);
        $this->assertSame(['model' => 'm', 'messages' => []], json_decode($captured['options']['body'], true));
    }

    public function testThrowsOnHttpError(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":{"code":500}}', ['http_code' => 500]));

        $this->expectException(ClassifierException::class);
        $this->client($http)->chatCompletion([]);
    }

    public function testThrowsOnTransportError(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('network down');
        });

        $this->expectException(ClassifierException::class);
        $this->client($http)->chatCompletion([]);
    }

    public function testThrowsOnNonJsonEnvelope(): void
    {
        $http = new MockHttpClient(new MockResponse('not json', ['http_code' => 200]));

        $this->expectException(ClassifierException::class);
        $this->client($http)->chatCompletion([]);
    }
}
