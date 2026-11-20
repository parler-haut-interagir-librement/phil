<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use LogicException;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\ClaimsProvider;
use Phil\IdentityProviderBundle\Application\Oidc\UserClaims;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The claims of userinfo: those of the scopes, then those of each provider, none answering a claim already set.
 */
#[CoversClass(UserClaims::class)]
final class UserClaimsTest extends TestCase
{
    public function testTheProvidersAddTheirClaimsInOrder(): void
    {
        $account = new Services()->account();
        $asked = [];
        $claims = new UserClaims([
            $this->provider(static function (Account $account, array $scopes, string $clientId) use (&$asked): array {
                $asked[] = [$account->id(), $scopes, $clientId];

                return \in_array('profile', $scopes, true) ? ['locale' => 'fr-CA'] : [];
            }),
            $this->provider(static fn (): array => ['groups' => ['a', 'b']]),
        ]);

        self::assertSame(['sub' => $account->id(), 'email' => 'ada@example.com', 'email_verified' => false, 'name' => 'Ada', 'locale' => 'fr-CA', 'groups' => ['a', 'b']], $claims->of($account, ['openid', 'email', 'profile'], 'app'));
        self::assertSame(['sub' => $account->id(), 'groups' => ['a', 'b']], $claims->of($account, ['openid'], 'other'));
        self::assertSame([[$account->id(), ['openid', 'email', 'profile'], 'app'], [$account->id(), ['openid'], 'other']], $asked);
        self::assertSame(['sub' => $account->id()], new UserClaims()->of($account, ['openid'], 'app'));
    }

    public function testAProviderMayNotAnswerAClaimTheProviderSets(): void
    {
        $account = new Services()->account();

        foreach (['sub', 'acr', 'email', 'phone_number'] as $name) {
            try {
                new UserClaims([$this->provider(static fn (): array => [$name => 'x'])])->of($account, ['openid'], 'app');
                self::fail('Accepted '.$name);
            } catch (LogicException $logicException) {
                self::assertStringContainsString('"'.$name.'"', $logicException->getMessage());
            }
        }

        $this->expectException(LogicException::class);
        new UserClaims([$this->provider(static fn (): array => ['locale' => 'en']), $this->provider(static fn (): array => ['locale' => 'fr'])])->of($account, ['openid'], 'app');
    }

    /**
     * @param callable(Account, list<string>, string): array<string, mixed> $claims
     */
    private function provider(callable $claims): ClaimsProvider
    {
        return new readonly class($claims) implements ClaimsProvider {
            /** @var callable(Account, list<string>, string): array<string, mixed> */
            private mixed $claims;

            /**
             * @param callable(Account, list<string>, string): array<string, mixed> $claims
             */
            public function __construct(callable $claims)
            {
                $this->claims = $claims;
            }

            #[Override]
            public function claims(Account $account, array $scopes, string $clientId): array
            {
                return ($this->claims)($account, $scopes, $clientId);
            }
        };
    }
}
