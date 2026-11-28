<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Fixture;

use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkedIdentityRepository;
use Phil\IdentityProviderGoogleBundle\Application\LinkedSessions;
use Phil\IdentityProviderGoogleBundle\Application\ReplayGuard;
use Phil\IdentityProviderGoogleBundle\Application\SecurityEventReceiver;
use Phil\IdentityProviderGoogleBundle\Application\SecurityEventTokenReader;
use Phil\IdentityProviderGoogleBundle\Application\TransmitterSettings;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Cache\CacheSubjectHolds;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * A receiver of the local Google's security events, as the bundle's services build it, over the identity bundle's
 * stores in memory: an account linked to `FakeGoogle::SUBJECT` with two open sessions.
 */
final readonly class RiscHarness
{
    public FakeGoogle $google;

    public MockClock $clock;

    public ArrayAdapter $cache;

    public TransmitterSettings $transmitter;

    public SecurityEventTokenReader $reader;

    public InMemoryAccountRepository $accounts;

    public InMemoryLinkedIdentityRepository $identities;

    public InMemoryAccountSessionRepository $sessions;

    public CacheSubjectHolds $holds;

    public EventDispatcher $dispatcher;

    public SecurityEventReceiver $receiver;

    public Account $account;

    public function __construct(bool $withSessions = true)
    {
        $this->google = new FakeGoogle();
        $this->clock = new MockClock('@'.FakeGoogle::NOW);
        $this->cache = new ArrayAdapter();
        $transport = $this->google->transport();
        $discovery = new OidcDiscovery($transport, $this->cache, TransmitterSettings::GOOGLE_CONFIGURATION_URL, TransmitterSettings::GOOGLE_ISSUER, 86_400, 'test.risc', ['jwks_uri']);
        $this->transmitter = TransmitterSettings::google([FakeGoogle::CLIENT_ID]);
        $this->reader = new SecurityEventTokenReader(new OidcSignatureVerifier($discovery, $this->cache, $transport, ['RS256'], 3600, true, $this->clock), $this->transmitter, $this->clock);

        $this->accounts = new InMemoryAccountRepository();
        $this->identities = new InMemoryLinkedIdentityRepository();
        $this->sessions = new InMemoryAccountSessionRepository();
        $this->account = Account::register('01JDTESTACCOUNT00000000000', 'ada@gmail.com', $this->clock->now());
        $this->accounts->save($this->account);
        $this->identities->save(LinkedIdentity::link($this->account->id(), 'google', FakeGoogle::SUBJECT, $this->clock->now()));
        $this->sessions->save(
            AccountSession::open('session-1', $this->account->id(), 'device-1', ['pwd'], false, $this->clock->now()),
            AccountSession::open('session-2', $this->account->id(), 'device-2', ['pwd'], false, $this->clock->now()),
        );

        $this->holds = new CacheSubjectHolds(new ArrayAdapter());
        $this->dispatcher = new EventDispatcher();
        $this->receiver = new SecurityEventReceiver(
            $this->reader,
            new ReplayGuard($this->cache),
            new LinkedSessions($this->identities, $this->accounts, $withSessions ? new Sessions($this->sessions, new Journal(new NullAuditRecorder()), $this->clock) : null),
            $this->transmitter,
            $this->holds,
            $this->dispatcher,
        );
    }

    /**
     * A token of one event about the subject, as Google signs it.
     *
     * @param array<string, mixed> $event  more of the event
     * @param array<string, mixed> $claims more of the token
     */
    public static function token(string $type, array $event = [], array $claims = [], string $subject = FakeGoogle::SUBJECT): string
    {
        return FakeGoogle::sign(array_replace([
            'iss' => TransmitterSettings::GOOGLE_ISSUER,
            'aud' => FakeGoogle::CLIENT_ID,
            'iat' => FakeGoogle::NOW,
            'jti' => bin2hex(random_bytes(8)),
            'events' => ['https://schemas.openid.net/secevent/'.$type => array_replace(['subject' => ['subject_type' => 'iss-sub', 'iss' => 'https://accounts.google.com/', 'sub' => $subject]], $event)],
        ], $claims), ['typ' => 'secevent+jwt']);
    }

    /**
     * @return list<string> the identifiers of the open sessions of the account
     */
    public function openSessions(): array
    {
        return array_map(static fn (AccountSession $session): string => $session->id(), $this->sessions->openFor($this->account->id()));
    }
}
