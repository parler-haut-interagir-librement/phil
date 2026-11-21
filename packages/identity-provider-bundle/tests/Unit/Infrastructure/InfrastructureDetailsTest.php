<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Federation\Contract\ExternalProvider;
use Phil\IdentityProviderBundle\Infrastructure\Federation\ExternalProviders;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\ClientCredentials;
use Phil\IdentityProviderBundle\Infrastructure\Mailer\MailerAccountMailer;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\DeviceName;
use Phil\IdentityProviderBundle\Infrastructure\Security\Proofs;
use Phil\IdentityProviderBundle\Infrastructure\Security\StepUpVoter;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ArrayChallengeStore;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingTransport;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The details of the adapters: the order of the providers, the votes with their reasons, the text cut at its limit,
 * and the exact parameters of each message.
 */
#[CoversClass(ClientCredentials::class)]
#[CoversClass(DeviceName::class)]
#[CoversClass(ExternalProviders::class)]
#[CoversClass(MailerAccountMailer::class)]
#[CoversClass(StepUpVoter::class)]
final class InfrastructureDetailsTest extends TestCase
{
    public function testTheProvidersAreAListSortedByName(): void
    {
        $providers = new ExternalProviders([$this->provider('zeta'), $this->provider('alpha'), $this->provider('mid')]);

        self::assertSame(['alpha', 'mid', 'zeta'], array_map(static fn (ExternalProvider $provider): string => $provider->name(), $providers->all()));
        self::assertSame([0, 1, 2], array_keys($providers->all()));
        self::assertSame('mid', $providers->get('mid')?->name());
    }

    public function testTheStepUpLastsFiveMinutesByDefaultAndTakesEveryStrongMethod(): void
    {
        $services = new Services();
        $voter = new StepUpVoter($services->clock);
        $account = $services->account();
        $now = $services->clock->now()->getTimestamp();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $account, ['pwd' => $now - 300])->result);
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, $account, ['pwd' => $now - 301])->result);

        foreach ([...Proofs::STRONG, 'pwd'] as $method) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $account, [$method => $now])->result, $method);
        }

        $account->changePassword(null, $services->clock->now());

        foreach ([...Proofs::STRONG, 'pwd', '*'] as $method) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $account, [$method => $now])->result, $method);
        }
    }

    public function testTheStepUpGivesItsReason(): void
    {
        $services = new Services();
        $voter = new StepUpVoter($services->clock);
        $account = $services->account();
        $now = $services->clock->now()->getTimestamp();

        self::assertSame(['The account proved a strong enough method recently.'], $this->vote($voter, $account, ['pwd' => $now])->reasons);
        self::assertSame(['The account must prove its strongest method again.'], $this->vote($voter, $account, [])->reasons);
    }

    public function testTheStepUpAsksNothingOfAVoteThatIsNotGiven(): void
    {
        $services = new Services();
        $voter = new StepUpVoter($services->clock);
        $account = $services->account();
        $now = $services->clock->now()->getTimestamp();
        $granted = new UsernamePasswordToken(new AccountUser($account), 'main');
        $granted->setAuthenticationProofs(['pwd' => $now]);

        $denied = new UsernamePasswordToken(new AccountUser($account), 'main');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($granted, null, [StepUpVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($denied, null, [StepUpVoter::ATTRIBUTE]));
    }

    public function testAnAccountWithAStrongMethodMustProveItAgain(): void
    {
        $services = new Services();
        $passkeys = new InMemoryPasskeyRepository();
        $factors = new SecondFactors(new InMemorySecondFactorRepository(), new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
        $voter = new StepUpVoter($services->clock, $factors, new Passkeys(new RelyingParty('example.com', 'Example', ['https://example.com']), $passkeys, $services->accounts, new ArrayChallengeStore(), $services->journal, $services->clock));
        $account = $services->account();
        $now = $services->clock->now()->getTimestamp();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, $account, ['pwd' => $now])->result);
        self::assertSame(['The account proved a strong enough method recently.'], $this->vote($voter, $account, ['pwd' => $now])->reasons);
    }

    public function testADeviceIsNamedFromTheFirst512Bytes(): void
    {
        self::assertSame('Firefox', DeviceName::of(str_repeat('x', 504).'Firefox/'));
        self::assertSame('Browser', DeviceName::of(str_repeat('x', 505).'Firefox/'));
        self::assertSame('Firefox', DeviceName::of('Firefox/'.str_repeat('x', 600)));
    }

    public function testAnEmailCarriesItsSiteInTheSubjectAndItsSessionInTheContext(): void
    {
        $mailer = new RecordingTransport();
        $translator = new class implements TranslatorInterface {
            /** @var list<array{string, array<array-key, mixed>, ?string}> */
            public array $asked = [];

            /**
             * @param array<array-key, mixed> $parameters
             */
            #[Override]
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $this->asked[] = [$id, $parameters, $domain];

                return 'subject';
            }

            #[Override]
            public function getLocale(): string
            {
                return 'en';
            }
        };
        $createdAt = new DateTimeImmutable('2026-11-14 10:00:00');
        $account = Account::register('01JCZ6Q7Y3W0000000000000AA', 'ada@example.com', $createdAt, 'Ada');

        $accountMailer = new MailerAccountMailer($mailer, $mailer, $translator, 'accounts@example.com', 'Example');
        $accountMailer->sendRiskySignIn($account, AccountSession::open('s', $account->id(), 'Safari on iOS', [], true, $createdAt));
        $accountMailer->sendVerification($account, 'https://x/verify');

        $email = $mailer->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertEquals(['device' => 'Safari on iOS', 'at' => $createdAt, 'site' => 'Example', 'name' => 'Ada'], $email->getContext());
        self::assertSame([['email.risky_sign_in.subject', ['%site%' => 'Example'], 'phil_identity_provider'], ['email.verify_email.subject', ['%site%' => 'Example'], 'phil_identity_provider']], $translator->asked);
    }

    public function testTheBasicCredentialsAreReadThroughTheirSpaces(): void
    {
        foreach (['Basic ', 'basic ', 'Basic  '] as $prefix) {
            $credentials = ClientCredentials::from(new Request(server: ['HTTP_AUTHORIZATION' => $prefix.base64_encode('app:secret')]));
            self::assertSame(['app', 'secret', true], [$credentials->clientId, $credentials->secret, $credentials->basic], $prefix);
        }

        $credentials = ClientCredentials::from(new Request(server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('app:secret').' ']));
        self::assertSame('secret', $credentials->secret);

        $credentials = ClientCredentials::from(new Request(server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('app:sec:ret')]));
        self::assertSame(['app', 'sec:ret'], [$credentials->clientId, $credentials->secret]);
    }

    /**
     * @param array<string, int> $proofs
     */
    private function vote(StepUpVoter $voter, Account $account, array $proofs): Vote
    {
        $token = new UsernamePasswordToken(new AccountUser($account), 'main');
        $token->setAuthenticationProofs($proofs);

        $vote = new Vote();
        $voter->vote($token, null, [StepUpVoter::ATTRIBUTE], $vote);

        return $vote;
    }

    private function provider(string $name): ExternalProvider
    {
        return new readonly class($name) implements ExternalProvider {
            public function __construct(private string $name)
            {
            }

            #[Override]
            public function name(): string
            {
                return $this->name;
            }

            #[Override]
            public function label(): string
            {
                return ucfirst($this->name);
            }

            #[Override]
            public function start(Request $request, string $callbackUrl): Response
            {
                return new Response();
            }

            #[Override]
            public function finish(Request $request, string $callbackUrl): ExternalIdentity
            {
                return new ExternalIdentity($this->name, 'subject');
            }
        };
    }
}
