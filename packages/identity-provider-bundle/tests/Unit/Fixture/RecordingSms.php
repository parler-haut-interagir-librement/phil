<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;

/**
 * A gateway that keeps the messages it would send, or refuses them, for the tests.
 */
final class RecordingSms implements SmsSender
{
    /** @var list<array{string, string}> */
    public array $sent = [];

    public bool $fail = false;

    #[Override]
    public function send(string $phone, string $message): void
    {
        if ($this->fail) {
            throw DeliveryFailed::because('Refused by the test.');
        }

        $this->sent[] = [$phone, $message];
    }

    public function lastCode(): string
    {
        $last = end($this->sent);

        return false === $last ? '' : (string) preg_replace('/\D/', '', $last[1]);
    }
}
