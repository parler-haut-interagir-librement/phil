<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequest;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequestParser;
use Phil\IdentityProviderBundle\Domain\Oidc\QueryString;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

/**
 * The authorization endpoint's decisions: the request read by `AuthorizationRequestParser`, the consent each account
 * gives each client — none asked of a first-party client —, and the code issued once the person is signed in and
 * consented, sent back with the state and the issuer (RFC 9207).
 */
final readonly class Authorization
{
    private AuthorizationRequestParser $parser;

    public function __construct(
        private ClientRepository $clients,
        private GrantRepository $grants,
        private ProviderSettings $settings,
        private ClockInterface $clock,
        private Journal $journal,
    ) {
        $this->parser = new AuthorizationRequestParser($settings->scopes, $settings->nonceWithoutPkce);
    }

    /**
     * @param array<array-key, mixed> $query
     */
    public function parse(array $query): AuthorizationRequest
    {
        return $this->parser->parse($query, $this->clients->get(...));
    }

    public function client(AuthorizationRequest $request): Client
    {
        return $this->clients->get($request->clientId) ?? throw AuthorizationError::shown('invalid_client', 'The client is unknown.');
    }

    public function needsConsent(Account $account, AuthorizationRequest $request): bool
    {
        if ($request->prompts('consent')) {
            return true;
        }

        if ($this->client($request)->isFirstParty()) {
            return false;
        }

        return !($this->clients->consent($account->id(), $request->clientId)?->covers($request->scopes) ?? false);
    }

    public function grantConsent(Account $account, AuthorizationRequest $request): void
    {
        $now = $this->clock->now();
        $consent = $this->clients->consent($account->id(), $request->clientId);

        if (!$consent instanceof Consent) {
            $consent = Consent::grant($account->id(), $request->clientId, $request->scopes, $now);
        } else {
            $consent->extend($request->scopes, $now);
        }

        $this->clients->saveConsent($consent);
        $this->journal->record(IdentityEventType::ConsentGranted, $account->id(), ['client' => $request->clientId]);
    }

    /**
     * @return list<Consent>
     */
    public function consentsOf(Account $account): array
    {
        return $this->clients->consentsOf($account->id());
    }

    /**
     * Withdraws the consent given to a client and revokes its tokens.
     */
    public function withdrawConsent(Account $account, string $clientId): void
    {
        $consent = $this->clients->consent($account->id(), $clientId);

        if ($consent instanceof Consent) {
            $this->clients->removeConsent($consent);
        }

        $now = $this->clock->now();

        foreach ($this->grants->activeFor($account->id(), $clientId) as $token) {
            $token->revoke($now);
            $this->grants->saveToken($token);
        }

        $this->journal->record(IdentityEventType::ConsentWithdrawn, $account->id(), ['client' => $clientId]);
    }

    /**
     * The address the person goes back to, with a new code.
     *
     * @param list<string> $methods
     */
    public function issueCode(AuthorizationRequest $request, Account $account, DateTimeImmutable $authTime, array $methods, ?string $sessionId): string
    {
        $code = Base64Url::encode(random_bytes(32));
        $now = $this->clock->now();
        $this->grants->saveCode(AuthorizationCode::issue(
            $code,
            $request->clientId,
            $account->id(),
            $request->redirectUri,
            $request->scopes,
            $request->nonce,
            $request->codeChallenge,
            $authTime,
            $methods,
            $sessionId,
            new Ulid()->toBase32(),
            $now->modify(\sprintf('+%d seconds', $this->settings->codeLifetime)),
        ));

        return QueryString::append($request->redirectUri, array_filter(['code' => $code, 'state' => $request->state, 'iss' => $this->settings->issuer], static fn (?string $value): bool => null !== $value));
    }

    /**
     * The address an error goes back to, for `prompt=none` that cannot be answered without the person.
     */
    public function errorRedirect(AuthorizationRequest $request, string $error): string
    {
        return QueryString::append($request->redirectUri, array_filter(['error' => $error, 'state' => $request->state, 'iss' => $this->settings->issuer], static fn (?string $value): bool => null !== $value));
    }
}
