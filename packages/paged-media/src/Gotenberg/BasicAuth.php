<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use SensitiveParameter;

/**
 * The credentials of a Gotenberg started with `--api-enable-basic-auth`.
 */
final readonly class BasicAuth
{
    public function __construct(
        public string $username,
        #[SensitiveParameter]
        private string $password,
    ) {
        if ('' === $username || str_contains($username, ':')) {
            throw InvalidRequest::because('A basic authentication user name is not empty and holds no colon.');
        }
    }

    /**
     * The user name and the password, as Symfony HttpClient's `auth_basic` option takes them.
     *
     * @return array{string, string}
     */
    public function credentials(): array
    {
        return [$this->username, $this->password];
    }
}
