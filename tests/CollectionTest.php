<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestFactoryInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\StateStorage\StateStorageInterface;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;
use Yiisoft\Yii\AuthClient\Tests\Data\TestClient;

class CollectionTest extends TestCase
{
    private function getRequestFactory(): RequestFactoryInterface
    {
        return new Psr17Factory();
    }

    private function getStateStorage(): StateStorageInterface
    {
        return new SessionStateStorage(new Session());
    }

    private function getTestClient(): TestClient
    {
        $httpClient = $this->getMockBuilder(Client::class)->getMock();
        return new TestClient($httpClient, $this->getRequestFactory(), $this->getStateStorage());
    }

    public function testSetGet(): void
    {
        $collection = new Collection([]);

        $clients = [
            'testClient1' => $this->getTestClient(),
            'testClient2' => $this->getTestClient(),
        ];
        $collection->setClients($clients);
        $this->assertEquals($clients, $collection->getClients(), 'Unable to setup clients!');
    }

    public function testGetProviderByName(): void
    {
        $clientId = 'testClientId';
        $client = $this->getTestClient();
        $clients = [
            $clientId => $client,
        ];
        $collection = new Collection($clients);

        $this->assertEquals($client, $collection->getClient($clientId), 'Unable to get client by id!');
    }

    public function testHasProvider(): void
    {
        $clientName = 'testClientName';
        $collection = new Collection(
            [
                $clientName => $this->getTestClient(),
            ]
        );

        $this->assertTrue($collection->hasClient($clientName), 'Existing client check fails!');
        $this->assertFalse($collection->hasClient('nonExistingClientName'), 'Not existing client check fails!');
    }
}
