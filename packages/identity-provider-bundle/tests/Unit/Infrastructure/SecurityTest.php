<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Federation\ExternalProviders;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\ClientCredentials;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\ReAuthenticationEntryPoint;
use Phil\IdentityProviderBundle\Infrastructure\Security\StepUpVoter;
use Phil\IdentityProviderBundle\Tests\Functional\App\FakeProvider;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ArrayChallengeStore;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(StepUpVoter::class)]
#[CoversClass(ClientCredentials::class)]
#[CoversClass(ReAuthenticationEntryPoint::class)]
#[CoversClass(ExternalProviders::class)]
final class SecurityTest extends TestCase
{
    public function testStepUpAsksForTheStrongestMethodOfTheAccount(): void
    {
        $services = new Services();
        $factorStore = new InMemorySecondFactorRepository();
        $passkeyStore = new InMemoryPasskeyRepository();
        $factors = new SecondFactors($factorStore, new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
        $passkeys = new Passkeys(new RelyingParty('example.com', 'Example', ['https://example.com']), $passkeyStore, $services->accounts, new ArrayChallengeStore(), $services->journal, $services->clock);
        $voter = new StepUpVoter($services->clock, $factors, $passkeys, 300);
        $account = $services->account();
        $now = $services->clock->now()->getTimestamp();
        $vote = static function (array $proofs) use ($voter, $account): array {
            $token = new UsernamePasswordToken(new AccountUser($account), 'main');
            $token->setAuthenticationProofs($proofs);

            $vote = new Vote();

            return [$voter->vote($token, null, [StepUpVoter::ATTRIBUTE], $vote), $vote->reAuthenticationAttribute];
        };

        self::assertSame([VoterInterface::ACCESS_GRANTED, null], $vote(['pwd' => $now - 300]));
        self::assertSame([VoterInterface::ACCESS_DENIED, StepUpVoter::ATTRIBUTE], $vote(['pwd' => $now - 301]));
        self::assertSame([VoterInterface::ACCESS_DENIED, StepUpVoter::ATTRIBUTE], $vote(['*' => $now]));

        $passkeyStore->save(Passkey::register($account->id(), new RegisteredCredential('c', 'k', 0, str_repeat("\0", 16), false, false, false), 'Key', $services->clock->now()));
        self::assertSame([VoterInterface::ACCESS_DENIED, StepUpVoter::ATTRIBUTE], $vote(['pwd' => $now]));
        self::assertSame([VoterInterface::ACCESS_GRANTED, null], $vote(['hwk' => $now, 'pwd' => $now]));
        $passkeyStore->reset();

        $totp = Totp::fromBase32($factors->enroll($account)['secret']);
        $factors->confirm($account, $totp->at($now), false);
        self::assertSame([VoterInterface::ACCESS_DENIED, StepUpVoter::ATTRIBUTE], $vote(['pwd' => $now]));
        self::assertSame([VoterInterface::ACCESS_GRANTED, null], $vote(['otp' => $now]));
        $factors->disableTotp($account);

        $account->changePassword(null, $services->clock->now());
        self::assertSame([VoterInterface::ACCESS_GRANTED, null], $vote(['*' => $now]));

        $other = new UsernamePasswordToken(new InMemoryUser('x', null), 'main');
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($other, null, [StepUpVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($other, null, ['ROLE_USER']));
        self::assertSame(VoterInterface::ACCESS_DENIED, new StepUpVoter($services->clock)->vote(new UsernamePasswordToken(new AccountUser($account), 'main'), null, [StepUpVoter::ATTRIBUTE]));
    }

    public function testClientCredentials(): void
    {
        $basic = ClientCredentials::from(new Request(server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('my%20app:s%3Acret')]));
        self::assertSame(['my app', 's:cret', true], [$basic->clientId, $basic->secret, $basic->basic]);

        $post = ClientCredentials::from(new Request(request: ['client_id' => 'app', 'client_secret' => 'secret']));
        self::assertSame(['app', 'secret', false], [$post->clientId, $post->secret, $post->basic]);

        $public = ClientCredentials::from(new Request(request: ['client_id' => 'spa']));
        self::assertSame(['spa', null, false], [$public->clientId, $public->secret, $public->basic]);

        foreach ([
            'invalid_request' => [new Request(request: ['client_secret' => 'x'], server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('a:b')]), new Request(), new Request(request: ['client_id' => '']), new Request(request: ['client_id' => 'a', 'client_secret' => ['x']])],
            'invalid_client' => [new Request(server: ['HTTP_AUTHORIZATION' => 'Basic !!']), new Request(server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('nocolon')]), new Request(server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode(':secret')])],
        ] as $error => $requests) {
            foreach ($requests as $request) {
                try {
                    ClientCredentials::from($request);
                    self::fail($error);
                } catch (OAuthError $oauthError) {
                    self::assertSame($error, $oauthError->error);
                }
            }
        }
    }

    public function testTheReAuthenticationEntryPointAndTheProviders(): void
    {
        $routes = new RouteCollection();
        $routes->add(ReAuthenticationEntryPoint::ROUTE, new Route('/reauthenticate'));

        $response = new ReAuthenticationEntryPoint(new UrlGenerator($routes, new RequestContext()))->startReAuthentication(new Request(), new UsernamePasswordToken(new InMemoryUser('x', null), 'main'));

        self::assertSame('/reauthenticate', $response->headers->get('Location'));

        $providers = new ExternalProviders([new FakeProvider()]);
        self::assertSame('fake', $providers->get('fake')?->name());
        self::assertNull($providers->get('other'));
        self::assertCount(1, $providers->all());
        self::assertSame([], new ExternalProviders()->all());
    }
}
