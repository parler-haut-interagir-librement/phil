<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\CredentialResolvers;
use Phil\Checkin\Tests\Unit\Fixture\FixedCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CredentialResolvers::class)]
final class CredentialResolversTest extends TestCase
{
    public function testTheFirstResolverThatKnowsTheCodeAnswers(): void
    {
        $first = Credential::of('first');
        $resolvers = new CredentialResolvers([
            new FixedCredentials(['B' => Credential::of('only-b')]),
            new FixedCredentials(['A' => $first]),
            new FixedCredentials(['A' => Credential::of('second')]),
        ]);

        self::assertSame($first, $resolvers->resolve(CredentialCode::of('A')));
        self::assertSame('only-b', $resolvers->resolve(CredentialCode::of('B'))?->id);
        self::assertNull($resolvers->resolve(CredentialCode::of('C')));
    }

    public function testWithoutResolversNoCodeIsKnown(): void
    {
        self::assertNull(new CredentialResolvers()->resolve(CredentialCode::of('A')));
    }
}
