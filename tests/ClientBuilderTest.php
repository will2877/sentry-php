<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Sentry\Transport\TransportInterface;

final class ClientBuilderTest extends TestCase
{
    public function testGetOptions()
    {
        $options = new Options();
        $clientBuilder = new ClientBuilder($options);

        $this->assertSame($options, $clientBuilder->getOptions());
    }

    public function testHttpTransportIsUsedWhenServerIsConfigured(): void
    {
        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);

        $transport = $this->getTransport($clientBuilder->getClient());

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testNullTransportIsUsedWhenNoServerIsConfigured(): void
    {
        $clientBuilder = new ClientBuilder();

        $transport = $this->getTransport($clientBuilder->getClient());

        $this->assertInstanceOf(NullTransport::class, $transport);
    }

    public function testClientBuilderFallbacksToDefaultSdkIdentifierAndVersion(): void
    {
        $callbackCalled = false;

        $options = new Options();
        $options->setBeforeSendCallback(function (Event $event) use (&$callbackCalled) {
            $callbackCalled = true;

            $this->assertSame(Client::SDK_IDENTIFIER, $event->getSdkIdentifier());
            $this->assertSame(Client::SDK_VERSION, $event->getSdkVersion());

            return null;
        });

        (new ClientBuilder($options))->getClient()->captureMessage('test');

        $this->assertTrue($callbackCalled, 'Callback not invoked, no assertions performed');
    }

    public function testClientBuilderSetsSdkIdentifierAndVersion(): void
    {
        $callbackCalled = false;

        $options = new Options();
        $options->setBeforeSendCallback(function (Event $event) use (&$callbackCalled) {
            $callbackCalled = true;

            $this->assertSame('sentry.test', $event->getSdkIdentifier());
            $this->assertSame('1.2.3-test', $event->getSdkVersion());

            return null;
        });

        (new ClientBuilder($options))
            ->setSdkIdentifier('sentry.test')
            ->setSdkVersion('1.2.3-test')
            ->getClient()
            ->captureMessage('test');

        $this->assertTrue($callbackCalled, 'Callback not invoked, no assertions performed');
    }

    public function testCreateWithNoOptionsIsTheSameAsDefaultOptions(): void
    {
        $this->assertEquals(
            new ClientBuilder(new Options()),
            ClientBuilder::create([])
        );
    }

    private function getTransport(Client $client): TransportInterface
    {
        $property = new \ReflectionProperty(Client::class, 'transport');

        $property->setAccessible(true);
        $value = $property->getValue($client);
        $property->setAccessible(false);

        return $value;
    }
}

final class StubIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
    }
}
