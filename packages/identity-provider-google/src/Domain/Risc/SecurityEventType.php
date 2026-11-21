<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Domain\Risc;

/**
 * The security events a transmitter sends, by the URI that names them in a security event token (RFC 8417): those of
 * Google's Cross-Account Protection (RISC) and the session revocation of the Shared Signals Framework (CAEP). An event
 * of another type is left out of the token, as RFC 8417 asks of a receiver that does not know it.
 */
enum SecurityEventType: string
{
    case SessionsRevoked = 'https://schemas.openid.net/secevent/risc/event-type/sessions-revoked';
    case TokensRevoked = 'https://schemas.openid.net/secevent/oauth/event-type/tokens-revoked';
    case TokenRevoked = 'https://schemas.openid.net/secevent/oauth/event-type/token-revoked';
    case AccountDisabled = 'https://schemas.openid.net/secevent/risc/event-type/account-disabled';
    case AccountEnabled = 'https://schemas.openid.net/secevent/risc/event-type/account-enabled';
    case AccountPurged = 'https://schemas.openid.net/secevent/risc/event-type/account-purged';
    case AccountCredentialChangeRequired = 'https://schemas.openid.net/secevent/risc/event-type/account-credential-change-required';
    case Verification = 'https://schemas.openid.net/secevent/risc/event-type/verification';
    case SessionRevoked = 'https://schemas.openid.net/secevent/caep/event-type/session-revoked';

    /**
     * Whether the sessions the subject opened here are closed: its sessions or tokens were revoked at the provider, or
     * its account there was disabled or deleted.
     */
    public function closesSessions(): bool
    {
        return match ($this) {
            self::SessionsRevoked, self::TokensRevoked, self::TokenRevoked, self::AccountDisabled, self::AccountPurged, self::SessionRevoked => true,
            self::AccountEnabled, self::AccountCredentialChangeRequired, self::Verification => false,
        };
    }
}
