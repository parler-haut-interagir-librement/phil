<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Event;

/**
 * What happened to an account. The value is the action written to the audit trail.
 */
enum IdentityEventType: string
{
    case AccountRegistered = 'identity.account.registered';
    case RegistrationForKnownEmail = 'identity.account.registration_for_known_email';
    case EmailVerified = 'identity.account.email_verified';
    case EmailChanged = 'identity.account.email_changed';
    case PasswordChanged = 'identity.password.changed';
    case PasswordResetRequested = 'identity.password.reset_requested';
    case PasswordReset = 'identity.password.reset';
    case SignInFailed = 'identity.sign_in.failed';
    case AccountLocked = 'identity.account.locked';
    case RiskySignIn = 'identity.sign_in.risky';
    case PasskeyAdded = 'identity.passkey.added';
    case PasskeyRemoved = 'identity.passkey.removed';
    case TotpEnabled = 'identity.totp.enabled';
    case TotpDisabled = 'identity.totp.disabled';
    case RecoveryCodesGenerated = 'identity.recovery_codes.generated';
    case RecoveryCodeUsed = 'identity.recovery_codes.used';
    case PhoneVerified = 'identity.phone.verified';
    case PhoneRemoved = 'identity.phone.removed';
    case LoginLinkSent = 'identity.login_link.sent';
    case SessionOpened = 'identity.session.opened';
    case SessionClosed = 'identity.session.closed';
    case IdentityLinked = 'identity.external.linked';
    case IdentityUnlinked = 'identity.external.unlinked';
    case ConsentGranted = 'identity.consent.granted';
    case ConsentWithdrawn = 'identity.consent.withdrawn';
    case TokensIssued = 'identity.oidc.tokens_issued';
    case TokenReuseDetected = 'identity.oidc.token_reuse';
    case TokenRevoked = 'identity.oidc.token_revoked';
    case ClientRegistered = 'identity.oidc.client_registered';
    case SigningKeyRotated = 'identity.oidc.key_rotated';
}
