<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ApiContractBundle\Infrastructure\Http\ActiveTenantResolver;
use Phil\ApiContractBundle\Infrastructure\Http\AnonymousCallers;
use Phil\ApiContractBundle\Infrastructure\Http\CallerScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyClientResolver;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemException;
use Phil\ApiContractBundle\Infrastructure\Http\TokenAttributeClientResolver;
use Phil\Idempotency\PayloadFingerprint;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(CallerScopeResolver::class)]
#[CoversClass(ActiveTenantResolver::class)]
#[CoversClass(TokenAttributeClientResolver::class)]
#[CoversClass(AnonymousCallers::class)]
final class CallerScopeResolverTest extends TestCase
{
    public function testTheScopeIsTheHashOfTheTenantTheClientAndTheUser(): void
    {
        $tokens = $this->signedIn('ada', ['client_id' => 'mobile']);
        $tenants = new TenantContext();
        $tenants->enter(TenantId::fromString('acme'));

        $resolver = new CallerScopeResolver($tokens, new ActiveTenantResolver($tenants), new TokenAttributeClientResolver($tokens));

        self::assertSame('caller:'.PayloadFingerprint::ofParts('tenant', 'acme', 'client', 'mobile', 'user', 'ada')->hex, $resolver->scope($this->request())->value);
        self::assertSame('caller:'.PayloadFingerprint::ofParts('user', 'ada')->hex, new CallerScopeResolver($tokens)->scope($this->request())->value);
    }

    public function testTwoTenantsNeverShareAScope(): void
    {
        $tokens = $this->signedIn('ada');
        $tenants = new TenantContext();
        $resolver = new CallerScopeResolver($tokens, new ActiveTenantResolver($tenants));

        $tenants->enter(TenantId::fromString('acme'));
        $acme = $resolver->scope($this->request());
        $tenants->enter(TenantId::fromString('globex'));
        $globex = $resolver->scope($this->request());
        $tenants->leave();
        $none = $resolver->scope($this->request());

        self::assertFalse($acme->equals($globex));
        self::assertFalse($acme->equals($none));
        self::assertSame('caller:'.PayloadFingerprint::ofParts('user', 'ada')->hex, $none->value);
        self::assertNull(new ActiveTenantResolver()->tenant($this->request()), 'Without phil/tenancy-bundle, no tenant.');
    }

    public function testTwoClientsOfOneUserNeverShareAScope(): void
    {
        $mobile = new CallerScopeResolver($this->signedIn('ada'), clients: $this->client('mobile'));
        $web = new CallerScopeResolver($this->signedIn('ada'), clients: $this->client('web'));

        self::assertFalse($mobile->scope($this->request())->equals($web->scope($this->request())));
    }

    public function testAClientWithoutAUserIsACaller(): void
    {
        $scope = new CallerScopeResolver(clients: $this->client('batch'))->scope($this->request());

        self::assertSame('caller:'.PayloadFingerprint::ofParts('client', 'batch')->hex, $scope->value);
    }

    public function testTheLabelsKeepThePartsApart(): void
    {
        $asTenant = new CallerScopeResolver($this->signedIn('ada'), $this->tenant('x'))->scope($this->request());
        $asClient = new CallerScopeResolver($this->signedIn('ada'), clients: $this->client('x'))->scope($this->request());

        self::assertFalse($asTenant->equals($asClient));
    }

    public function testAnAnonymousCallerIsRefusedByDefault(): void
    {
        foreach ([new CallerScopeResolver(), new CallerScopeResolver(new TokenStorage()), new CallerScopeResolver($this->tokens(new NullToken())), new CallerScopeResolver($this->tokens(new NullToken()), $this->tenant('acme'))] as $resolver) {
            try {
                $resolver->scope($this->request());
                self::fail('An anonymous caller was given a scope.');
            } catch (ProblemException $problemException) {
                self::assertSame([403, 'An Idempotency-Key is accepted from an authenticated caller only.'], [$problemException->problem->status, $problemException->problem->title]);
                self::assertSame(CallerScopeResolver::ANONYMOUS, $problemException->problem->title);
            }
        }
    }

    public function testAnAnonymousCallerByAddressWhenAskedFor(): void
    {
        $resolver = new CallerScopeResolver(anonymous: AnonymousCallers::ByIp);
        self::assertSame('caller:'.PayloadFingerprint::ofParts('ip', '10.0.0.1')->hex, $resolver->scope($this->request())->value);

        $noAddress = Request::create('/');
        $noAddress->server->remove('REMOTE_ADDR');
        self::assertSame('caller:'.PayloadFingerprint::ofParts('ip', 'unknown')->hex, $resolver->scope($noAddress)->value);

        $withinTenant = new CallerScopeResolver(tenants: $this->tenant('acme'), anonymous: AnonymousCallers::ByIp);
        self::assertSame('caller:'.PayloadFingerprint::ofParts('tenant', 'acme', 'ip', '10.0.0.1')->hex, $withinTenant->scope($this->request())->value);

        $signedIn = new CallerScopeResolver($this->signedIn('ada'), anonymous: AnonymousCallers::ByIp);
        self::assertSame('caller:'.PayloadFingerprint::ofParts('user', 'ada')->hex, $signedIn->scope($this->request())->value, 'A user is never scoped by address.');
        self::assertSame(['refuse', 'ip'], array_map(static fn (AnonymousCallers $case): string => $case->value, AnonymousCallers::cases()));
    }

    public function testTheEndpointsPolicyReplacesTheConfiguredOne(): void
    {
        $byIp = $this->request();
        $byIp->attributes->set(IdempotencyListener::ANONYMOUS_CALLERS, AnonymousCallers::ByIp);
        self::assertSame('caller:'.PayloadFingerprint::ofParts('ip', '10.0.0.1')->hex, new CallerScopeResolver()->scope($byIp)->value);

        $refused = $this->request();
        $refused->attributes->set(IdempotencyListener::ANONYMOUS_CALLERS, AnonymousCallers::Refuse);

        try {
            new CallerScopeResolver(anonymous: AnonymousCallers::ByIp)->scope($refused);
            self::fail('The endpoint refuses anonymous callers, whatever the option says.');
        } catch (ProblemException $problemException) {
            self::assertSame(403, $problemException->problem->status);
        }

        $notAPolicy = $this->request();
        $notAPolicy->attributes->set(IdempotencyListener::ANONYMOUS_CALLERS, 'ip');
        self::assertSame('caller:'.PayloadFingerprint::ofParts('ip', '10.0.0.1')->hex, new CallerScopeResolver(anonymous: AnonymousCallers::ByIp)->scope($notAPolicy)->value, 'A value that is not a case leaves the option in charge.');

        try {
            new CallerScopeResolver()->scope($notAPolicy);
            self::fail('A string in the attribute turned the refusal off.');
        } catch (ProblemException $problemException) {
            self::assertSame(403, $problemException->problem->status);
        }

        $signedIn = $this->request();
        $signedIn->attributes->set(IdempotencyListener::ANONYMOUS_CALLERS, AnonymousCallers::ByIp);
        self::assertSame('caller:'.PayloadFingerprint::ofParts('user', 'ada')->hex, new CallerScopeResolver($this->signedIn('ada'))->scope($signedIn)->value);
    }

    public function testTheClientIsAnAttributeOfTheToken(): void
    {
        self::assertNull(new TokenAttributeClientResolver()->client($this->request()));
        self::assertNull(new TokenAttributeClientResolver(new TokenStorage())->client($this->request()));
        self::assertNull(new TokenAttributeClientResolver($this->signedIn('ada'))->client($this->request()));
        self::assertSame('mobile', new TokenAttributeClientResolver($this->signedIn('ada', ['client_id' => 'mobile']))->client($this->request()));
        self::assertSame('mobile', new TokenAttributeClientResolver($this->signedIn('ada', ['azp' => 'mobile']), 'azp')->client($this->request()));
        self::assertNull(new TokenAttributeClientResolver($this->signedIn('ada', ['client_id' => 'mobile']), null)->client($this->request()));
        self::assertNull(new TokenAttributeClientResolver($this->signedIn('ada', ['client_id' => '']))->client($this->request()));
        self::assertNull(new TokenAttributeClientResolver($this->signedIn('ada', ['client_id' => 42]))->client($this->request()));
        self::assertSame('client_id', TokenAttributeClientResolver::DEFAULT_ATTRIBUTE);
    }

    private function request(): Request
    {
        return Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function signedIn(string $user, array $attributes = []): TokenStorage
    {
        $token = new UsernamePasswordToken(new InMemoryUser($user, null), 'main');
        $token->setAttributes($attributes);

        return $this->tokens($token);
    }

    private function tokens(NullToken|UsernamePasswordToken $token): TokenStorage
    {
        $tokens = new TokenStorage();
        $tokens->setToken($token);

        return $tokens;
    }

    private function tenant(string $tenant): IdempotencyTenantResolver
    {
        return new readonly class($tenant) implements IdempotencyTenantResolver {
            public function __construct(
                private string $tenant,
            ) {
            }

            #[Override]
            public function tenant(Request $request): string
            {
                return $this->tenant;
            }
        };
    }

    private function client(string $client): IdempotencyClientResolver
    {
        return new readonly class($client) implements IdempotencyClientResolver {
            public function __construct(
                private string $client,
            ) {
            }

            #[Override]
            public function client(Request $request): string
            {
                return $this->client;
            }
        };
    }
}
