<?php

declare(strict_types=1);

namespace Yiisoft\Yii\AuthClient;

use InvalidArgumentException;
use RuntimeException;

/**
 * Collection is a storage for all auth clients in the application.
 *
 * Example application configuration:
 *
 * ```php
 * 'authClients' => [
 *     'google' => [
 *         'class' => Yiisoft\Yii\AuthClient\Clients\Google::class,
 *         'setClientId()' => ['google_client_id'],
 *         'setClientSecret()' => ['google_client_secret'],
 *      ],
 *     'facebook' => [
 *         'class' => Yiisoft\Yii\AuthClient\Clients\Facebook::class,
 *         'setClientId()' => ['facebook_client_id'],
 *         'setClientSecret()' => ['facebook_client_secret'],
 *     ]
 *     ...
 * ]
 * ```
 */
final class Collection
{
    public function __construct(
        /**
         * @var array|AuthClientInterface[] list of Auth clients with their configuration in format: 'clientName' => [...]
         */
        private array $clients
    ) {
    }

    /**
     * @param string $name client name
     *
     * @throws InvalidArgumentException on non existing client request.
     *
     * @return AuthClientInterface auth client instance.
     */
    public function getClient(string $name): AuthClientInterface
    {
        if (!$this->hasClient($name)) {
            throw new InvalidArgumentException("Unknown auth client '{$name}'.");
        }

        $client = $this->clients[$name];
        if (!($client instanceof AuthClientInterface)) {
            throw new RuntimeException(
                'The Client should be a ClientInterface.'
            );
        }
        return $client;
    }

    /**
     * @return AuthClientInterface[] list of auth clients.
     */
    public function getClients(): array
    {
        $clients = [];
        /**
         * @psalm-suppress MixedAssignment  Unable to determine the type that $client is being assigned to
         */
        foreach ($this->clients as $name => $client) {
            /**
             * @psalm-suppress  MixedArgumentTypeCoercion - getClient expects string, but parent type array-key provided
             */
            $clients[$name] = $this->getClient($name);
        }

        return $clients;
    }

    /**
     * @param array $clients list of auth clients indexed by their names
     */
    public function setClients(array $clients): void
    {
        $this->clients = $clients;
    }

    /**
     * Checks if client exists in the hub.
     *
     * @param string $name client id.
     *
     * @return bool whether client exist.
     */
    public function hasClient(string $name): bool
    {
        return array_key_exists($name, $this->clients);
    }
}
