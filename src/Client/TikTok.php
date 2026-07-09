<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient\Client;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\RequestUtil;

/**
 * TikTok allows authentication via TikTok Login Kit.
 *
 * @see https://developers.tiktok.com/doc/login-kit-web
 * @see https://developers.tiktok.com/doc/tiktok-api-v2-get-user-info
 */
final class TikTok extends OAuth2
{
    private const USER_INFO_FIELDS = 'open_id,union_id,avatar_url,avatar_url_100,avatar_large_url,display_name,bio_description,profile_deep_link,is_verified';

    protected string $authUrl = 'https://www.tiktok.com/v2/auth/authorize/';

    protected string $tokenUrl = 'https://open.tiktokapis.com/v2/oauth/token/';

    protected string $endpoint = 'https://open.tiktokapis.com/v2/user/info/';

    #[\Override]
    public function buildAuthUrl(
        ServerRequestInterface $incomingRequest,
        array $params = []
    ): string {
        $defaultParams = [
            'client_key' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->getOauth2ReturnUrl(),
        ];

        /** @var string|null $authName */
        $authName = $incomingRequest->getAttribute(AuthAction::AUTH_NAME);
        if (is_string($authName) && $authName !== '') {
            $defaultParams['xoauth_displayname'] = $authName;
        }

        if ($this->getScope() !== '') {
            $defaultParams['scope'] = $this->getScope();
        }

        if ($this->validateAuthState) {
            $authState = $this->generateAuthState();
            $this->setState('authState', $authState);
            $defaultParams['state'] = $authState;
        }

        return RequestUtil::composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    #[\Override]
    public function fetchAccessToken(
        ServerRequestInterface $incomingRequest,
        string $authCode,
        array $params = []
    ): OAuthToken {
        $this->validateIncomingAuthState($incomingRequest);

        /** @var array<string, string|int|float|bool|null> $requestBody */
        $requestBody = array_merge(
            [
                'client_key' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $authCode,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->getOauth2ReturnUrl(),
            ],
            $params
        );

        return $this->sendTokenRequest($requestBody);
    }

    #[\Override]
    public function refreshAccessToken(OAuthToken $token): OAuthToken
    {
        return $this->sendTokenRequest([
            'client_key' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => (string)$token->getParam('refresh_token'),
        ]);
    }

    public function getCurrentUserJsonArray(
        OAuthToken $token,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
    ): array {
        $tokenString = (string)$token->getParam('access_token');

        if ($tokenString === '') {
            return [];
        }

        $url = $this->endpoint . '?fields=' . self::USER_INFO_FIELDS;
        $httpClient ??= $this->httpClient;
        $requestFactory ??= $this->requestFactory;

        $request = $requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $tokenString);

        try {
            $response = $httpClient->sendRequest($request);
            $body = $response->getBody()->getContents();
            if ($body !== '') {
                /** @var array<array-key, mixed>|string|int|float|bool|null $decoded */
                $decoded = json_decode($body, true);

                if (is_array($decoded)) {
                    return $this->normalizeUserInfoResponse($decoded);
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    protected function initUserAttributes(): array
    {
        $token = $this->getAccessToken();
        if ($token instanceof OAuthToken) {
            return $this->getCurrentUserJsonArray($token);
        }
        return [];
    }

    #[\Override]
    public function getButtonClass(): string
    {
        return 'btn btn-dark bi bi-tiktok';
    }

    /**
     * @return int[]
     *
     * @psalm-return array{popupWidth: 860, popupHeight: 680}
     */
    #[\Override]
    protected function defaultViewOptions(): array
    {
        return [
            'popupWidth' => 860,
            'popupHeight' => 680,
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'user.info.basic'
     */
    #[\Override]
    protected function getDefaultScope(): string
    {
        return 'user.info.basic';
    }

    #[\Override]
    public function getName(): string
    {
        return 'tiktok';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'TikTok';
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    private function sendTokenRequest(array $params): OAuthToken
    {
        $request = $this->requestFactory
            ->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        $token = new OAuthToken();

        try {
            $response = $this->httpClient->sendRequest($request);
            $decoded = json_decode($response->getBody()->getContents(), true);

            if (!is_array($decoded)) {
                return $token;
            }

            /** @var array<array-key, mixed>|string|int|float|bool|null $value */
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $token->setParam($key, $value);
                }
            }
        } catch (\Throwable) {
            return $token;
        }

        return $token;
    }

    private function validateIncomingAuthState(ServerRequestInterface $incomingRequest): void
    {
        if (!$this->validateAuthState) {
            return;
        }

        /** @var string|null $authState */
        $authState = $this->getState('authState');
        $queryParams = $incomingRequest->getQueryParams();
        $bodyParams = $incomingRequest->getParsedBody();
        $incomingState = $queryParams['state'] ?? (is_array($bodyParams) ? ($bodyParams['state'] ?? null) : null);

        if (!is_string($incomingState) || $incomingState === '' || $incomingState !== (string)$authState) {
            throw new InvalidArgumentException('Invalid auth state parameter.');
        }

        if ($authState === null || $authState === '') {
            throw new InvalidArgumentException('Invalid auth state parameter.');
        }

        $this->removeState('authState');
    }

    private function normalizeUserInfoResponse(array $response): array
    {
        /** @var array<array-key, mixed>|string|int|float|bool|null $data */
        $data = $response['data'] ?? null;
        $array = is_array($data) ? $data : [];

        /** @var array<array-key, mixed>|string|int|float|bool|null $user */
        $user = $array['user'] ?? null;

        return is_array($user) ? $user : $response;
    }
}
