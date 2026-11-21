<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Domain;

use Phil\IdentityProviderGoogle\Domain\Risc\SecurityEventType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityEventType::class)]
final class SecurityEventTypeTest extends TestCase
{
    public function testTheEventsThatCloseTheSessions(): void
    {
        $closing = array_values(array_filter(SecurityEventType::cases(), static fn (SecurityEventType $type): bool => $type->closesSessions()));

        self::assertSame([
            SecurityEventType::SessionsRevoked,
            SecurityEventType::TokensRevoked,
            SecurityEventType::TokenRevoked,
            SecurityEventType::AccountDisabled,
            SecurityEventType::AccountPurged,
            SecurityEventType::SessionRevoked,
        ], $closing);
    }
}
