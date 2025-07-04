<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

/**
 * Token represents OAuth token.
 */
final class OAuthToken
{
    /**
     * @var string key in {@see params} array, which stores token key.
     */
    private string $tokenParamKey = 'oauth_token';
    /**
     * @var string key in {@see params} array, which stores token secret key.
     */
    private string $tokenSecretParamKey = 'oauth_token_secret';
    /**
     * @var int object creation timestamp.
     */
    private readonly int $createTimestamp;

    /**
     * @var string|null key in {@see params} array, which stores token expiration duration.
     * If not set will attempt to fetch its value automatically.
     */
    private ?string $expireDurationParamKey = null;
    /**
     * @var array token parameters.
     */
    private array $params = [];

    public function __construct()
    {
        $this->createTimestamp = time();
    }

    /**
     * Returns the token secret value.
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedInferredReturnType
     * @return string token secret value.
     */
    public function getTokenSecret(): string
    {
        return $this->getParam($this->tokenSecretParamKey ?: 'oauth_token_secret');
    }

    /**
     * Sets token value.
     *
     * @param string $token token value.
     */
    public function setToken(string $token): void
    {
        $this->setParam($this->tokenParamKey ?: 'oauth_token', $token);
    }

    /**
     * Returns param by name.
     *
     * @param string $name param name.
     *
     * @return mixed param value.
     */
    public function getParam(string $name): mixed
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Sets token expire duration.
     *
     * @param int $expireDuration token expiration duration.
     */
    public function setExpireDuration(int $expireDuration): void
    {
        $this->setParam($this->getExpireDurationParamKey(), $expireDuration);
    }

    /**
     * @return string expire duration param key.
     */
    public function getExpireDurationParamKey(): string
    {
        if ($this->expireDurationParamKey === null) {
            $this->expireDurationParamKey = $this->defaultExpireDurationParamKey();
        }

        return $this->expireDurationParamKey;
    }

    /**
     * Fetches default expire duration param key.
     *
     * @return string expire duration param key.
     */
    protected function defaultExpireDurationParamKey(): string
    {
        $expireDurationParamKey = 'expires_in';
        /**
         * @var mixed $value
         */
        foreach ($this->getParams() as $name => $value) {
            if (!str_contains((string)$name, 'expir')) {
            } else {
                $expireDurationParamKey = (string)$name;
                break;
            }
        }
        return $expireDurationParamKey;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Sets param by name.
     *
     * @param string $name param name.
     * @param mixed $value param value,
     */
    public function setParam(string $name, mixed $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * Sets the token secret value.
     *
     * @param string $tokenSecret token secret.
     */
    public function setTokenSecret(string $tokenSecret): void
    {
        $this->setParam($this->tokenSecretParamKey ?: 'oauth_token_secret', $tokenSecret);
    }

    /**
     * @param array $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Checks if token is valid.
     *
     * @return bool is token valid.
     */
    public function getIsValid(): bool
    {
        $token = $this->getToken();

        return strlen($token ?? '') > 0 && !$this->getIsExpired();
    }

    /**
     * Returns token value.
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedInferredReturnType
     */
    public function getToken(): ?string
    {
        return $this->getParam($this->tokenParamKey);
    }

    /**
     * Checks if token has expired.
     *
     * @return bool is token expired.
     */
    public function getIsExpired(): bool
    {
        $expirationDuration = (int)$this->getExpireDuration();

        return time() >= ($this->createTimestamp + $expirationDuration);
    }

    /**
     * Returns the token expiration duration.
     *
     * return mixed token expiration duration.
     */
    public function getExpireDuration(): mixed
    {
        return $this->getParam($this->getExpireDurationParamKey());
    }
}
