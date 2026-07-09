<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Tests\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Factory\Factory as YiisoftFactory;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\AuthClient\Client\TikTok;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\SessionStateStorage;
use Yiisoft\Yii\AuthClient\Tests\Data\Session;

final class TikTokTest extends TestCase
{
    public function testBuildAuthUrlUsesTikTokClientKey(): void
    {
        $client = $this->createClient($this->createStub(ClientInterface::class));
        $client->setClientId('client-key');
        $client->setOauth2ReturnUrl('https://example.test/auth/tiktok');

        $url = $client->buildAuthUrl(new ServerRequest('GET', 'https://example.test/login'));
        parse_str((string)parse_url($url, PHP_URL_QUERY), $queryParams);

        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/', $url);
        $this->assertSame('client-key', $queryParams['client_key'] ?? null);
        $this->assertArrayNotHasKey('client_id', $queryParams);
        $this->assertSame('code', $queryParams['response_type'] ?? null);
        $this->assertSame('https://example.test/auth/tiktok', $queryParams['redirect_uri'] ?? null);
        $this->assertSame('user.info.basic', $queryParams['scope'] ?? null);
        $this->assertNotEmpty($queryParams['state'] ?? null);
    }

    public function testFetchAccessTokenUsesTikTokTokenRequestAndParsesJson(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(static function (RequestInterface $request): bool {
                parse_str((string)$request->getBody(), $bodyParams);

                return $request->getMethod() === 'POST'
                    && (string)$request->getUri() === 'https://open.tiktokapis.com/v2/oauth/token/'
                    && $request->getHeaderLine('Content-Type') === 'application/x-www-form-urlencoded'
                    && $bodyParams === [
                        'client_key' => 'client-key',
                        'client_secret' => 'client-secret',
                        'code' => 'auth-code',
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => 'https://example.test/auth/tiktok',
                    ];
            }))
            ->willReturn(new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 86400,
                ], JSON_THROW_ON_ERROR)
            ));

        $client = $this->createClient($httpClient);
        $client->setClientId('client-key');
        $client->setClientSecret('client-secret');
        $client->setOauth2ReturnUrl('https://example.test/auth/tiktok');

        $authUrl = $client->buildAuthUrl(new ServerRequest('GET', 'https://example.test/login'));
        parse_str((string)parse_url($authUrl, PHP_URL_QUERY), $queryParams);

        $token = $client->fetchAccessToken(
            (new ServerRequest('GET', 'https://example.test/auth/tiktok'))->withQueryParams([
                'state' => $queryParams['state'],
            ]),
            'auth-code'
        );

        $this->assertSame('access-token', $token->getParam('access_token'));
        $this->assertSame('refresh-token', $token->getParam('refresh_token'));
        $this->assertSame(86400, $token->getParam('expires_in'));
    }

    public function testGetCurrentUserJsonArrayReturnsEmptyArrayWhenTokenIsMissing(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects($this->never())
            ->method('sendRequest');

        $client = $this->createClient($httpClient);

        $this->assertSame([], $client->getCurrentUserJsonArray(new OAuthToken()));
    }

    public function testGetCurrentUserJsonArrayFetchesAndNormalizesUserInfo(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(static function (RequestInterface $request): bool {
                return $request->getMethod() === 'GET'
                    && (string)$request->getUri() === 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,avatar_url_100,avatar_large_url,display_name,bio_description,profile_deep_link,is_verified'
                    && $request->getHeaderLine('Authorization') === 'Bearer test-token';
            }))
            ->willReturn(new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'data' => [
                        'user' => [
                            'open_id' => 'open-id',
                            'display_name' => 'Ada',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            ));

        $client = $this->createClient($httpClient);
        $token = new OAuthToken();
        $token->setParam('access_token', 'test-token');

        $this->assertSame(
            [
                'open_id' => 'open-id',
                'display_name' => 'Ada',
            ],
            $client->getCurrentUserJsonArray($token)
        );
    }

    public function testClientMetadata(): void
    {
        $client = $this->createClient($this->createStub(ClientInterface::class));

        $this->assertSame('tiktok', $client->getName());
        $this->assertSame('TikTok', $client->getTitle());
        $this->assertSame('btn btn-dark bi bi-tiktok', $client->getButtonClass());
    }

    private function createClient(ClientInterface $httpClient): TikTok
    {
        $session = $this->createStub(SessionInterface::class);

        return new TikTok(
            $httpClient,
            new Psr17Factory(),
            new SessionStateStorage(new Session()),
            new YiisoftFactory(new Container(ContainerConfig::create())),
            $session,
        );
    }
}