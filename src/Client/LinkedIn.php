<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * LinkedIn allows authentication via LinkedIn OAuth.
 *
 * In order to use linkedIn OAuth you must register your application at <https://www.linkedin.com/secure/developer>.
 * @link https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?source=recommendations&tabs=HTTPS1
 * @link https://developer.linkedin.com/docs/oauth2
 * @link https://www.linkedin.com/secure/developer
 * @link https://developer.linkedin.com/docs/rest-api
 */
final class LinkedIn extends OAuth2
{
    protected string $authUrl = 'https://www.linkedin.com/oauth/v2/authorization';
    protected string $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
    protected string $endpoint = 'https://api.linkedin.com/v2';
    
    /**
     * Fetch current user information using PSR-18 HTTP Client and PSR-17 Request Factory.
     *
     * @param OAuthToken $token
     * @param ClientInterface $httpClient PSR-18 HTTP Client
     * @param RequestFactoryInterface $requestFactory PSR-17 Request Factory
     * @return array
     */
    public function getCurrentUserJsonArray(
        OAuthToken $token,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ): array {
        $tokenString = (string)$token->getParam('access_token');
        if (strlen($tokenString) === 0) {
            return [];
        }

        $request = $requestFactory->createRequest('GET', 'https://api.linkedin.com/v2/userinfo')
            ->withHeader('Authorization', 'Bearer ' . $tokenString);

        try {
            /** @var ResponseInterface $response */
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if (strlen($body) > 0) {
                return (array)json_decode($body, true);
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    public function getName(): string
    {
        return 'linkedin';
    }

    public function getTitle(): string
    {
        return 'LinkedIn';
    }

    /**
     * openid - Use your name and photo
     * profile - Use your name and photo
     * email - Use the primary email address associated with your LinkedIn account
     * w_member_social - Create, modify, and delete posts, comments, and reactions on your behalf
     *
     * @return string
     *
     * @psalm-return 'openid profile email w_member_social'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'openid profile email w_member_social';
    }
}
