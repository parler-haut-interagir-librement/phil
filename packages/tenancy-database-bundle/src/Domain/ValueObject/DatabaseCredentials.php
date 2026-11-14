<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Domain\ValueObject;

use LogicException;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;

/**
 * How to reach one dedicated database when it does not share the server or the account of the shared one: a user, a
 * password, and optionally a host and a port.
 *
 * **Never written out.** The password is a sensitive parameter, a dump shows it masked, and serializing the object
 * fails, so that it cannot reach a log, a cache entry or a Messenger message by accident. Stored, it is sealed: see
 * `CredentialSeal`.
 */
final readonly class DatabaseCredentials
{
    public const int MAX_PORT = 65535;

    /**
     * @throws InvalidValue when the user is empty, or a value holds a control character, or the port is out of range
     */
    public function __construct(
        public string $user,
        #[SensitiveParameter]
        public string $password,
        public ?string $host = null,
        public ?int $port = null,
    ) {
        if ('' === $user) {
            throw InvalidValue::because('A database user cannot be empty.');
        }

        if ('' === $host) {
            throw InvalidValue::because('A database host is null or a host name, never empty.');
        }

        foreach ([$user, $password, $host ?? ''] as $value) {
            if (1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw InvalidValue::because('A database user, password or host cannot contain control characters.');
            }
        }

        if (null !== $port && ($port < 1 || $port > self::MAX_PORT)) {
            throw InvalidValue::because(\sprintf('A database port is between 1 and %d; this one is %d.', self::MAX_PORT, $port));
        }
    }

    /**
     * @return array{user: string, password: string, host: ?string, port: ?int}
     */
    public function __debugInfo(): array
    {
        return ['user' => $this->user, 'password' => '***', 'host' => $this->host, 'port' => $this->port];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('Database credentials are never serialized; seal them with CredentialSeal to store them.');
    }
}
