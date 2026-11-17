<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Routing;

use Override;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Http\AuthenticatorRouteController;
use Phil\IdentityProviderBundle\Infrastructure\Http\ExternalController;
use Phil\IdentityProviderBundle\Infrastructure\Http\LoginLinkController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\AuthorizeController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\EndSessionController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\MetadataController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\TokenController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\UserinfoController;
use Phil\IdentityProviderBundle\Infrastructure\Http\PasskeyController;
use Phil\IdentityProviderBundle\Infrastructure\Http\PhoneController;
use Phil\IdentityProviderBundle\Infrastructure\Http\SessionController;
use Phil\IdentityProviderBundle\Infrastructure\Http\StepUpController;
use Phil\IdentityProviderBundle\Infrastructure\Http\TotpController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of the modules turned on, and only those: a module off has no address. The application imports them
 * once, behind its firewall:
 *
 *     # config/routes/phil_identity_provider.yaml
 *     phil_identity_provider:
 *         resource: .
 *         type: phil_identity_provider
 */
final class RouteLoader extends Loader
{
    public const string TYPE = 'phil_identity_provider';

    private const string ID = '[A-Za-z0-9_-]{1,64}';

    public function __construct(
        private readonly Modules $modules,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    #[Override]
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    #[Override]
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        $add = static function (string $name, string $path, string|array $controller, array $methods, array $requirements = []) use ($routes): void {
            $routes->add($name, new Route($path, ['_controller' => $controller], $requirements, methods: $methods));
        };
        $authenticator = AuthenticatorRouteController::class;

        if ($this->modules->has(Modules::ACCOUNTS)) {
            $add(AccountController::REGISTER, '/register', [AccountController::class, 'register'], ['GET', 'POST']);
            $add('phil_identity_provider_verify_email', '/verify-email', [AccountController::class, 'verifyEmail'], ['GET', 'POST']);
            $add('phil_identity_provider_resend_verification', '/account/verification', [AccountController::class, 'resendVerification'], ['POST']);
            $add(AccountController::ACCOUNT, '/account', [AccountController::class, 'account'], ['GET']);
            $add('phil_identity_provider_account_profile', '/account/profile', [AccountController::class, 'updateProfile'], ['POST']);
            $add('phil_identity_provider_account_email', '/account/email', [AccountController::class, 'changeEmail'], ['POST']);
        }

        if ($this->modules->has(Modules::PASSWORD)) {
            $add('phil_identity_provider_forgot_password', '/forgot-password', [AccountController::class, 'forgotPassword'], ['GET', 'POST']);
            $add('phil_identity_provider_reset_password', '/reset-password', [AccountController::class, 'resetPassword'], ['GET', 'POST']);
            $add('phil_identity_provider_account_password', '/account/password', [AccountController::class, 'changePassword'], ['POST']);
        }

        if ($this->modules->has(Modules::PASSKEYS)) {
            $add(PasskeyController::PAGE, '/account/passkeys', [PasskeyController::class, 'page'], ['GET']);
            $add('phil_identity_provider_passkeys_options', '/account/passkeys/options', [PasskeyController::class, 'registrationOptions'], ['POST']);
            $add('phil_identity_provider_passkeys_register', '/account/passkeys/register', [PasskeyController::class, 'register'], ['POST']);
            $add('phil_identity_provider_passkeys_rename', '/account/passkeys/{id}/rename', [PasskeyController::class, 'rename'], ['POST'], ['id' => '[0-9a-f]{64}']);
            $add('phil_identity_provider_passkeys_remove', '/account/passkeys/{id}/remove', [PasskeyController::class, 'remove'], ['POST'], ['id' => '[0-9a-f]{64}']);
            $add('phil_identity_provider_passkey_login_options', '/passkey/options', [PasskeyController::class, 'signInOptions'], ['POST']);
            $add('phil_identity_provider_passkey_login', '/passkey/login', $authenticator, ['POST']);
        }

        if ($this->modules->has(Modules::TOTP)) {
            $add(TotpController::SETUP, '/account/two-factor', [TotpController::class, 'setup'], ['GET']);
            $add('phil_identity_provider_totp_confirm', '/account/two-factor/confirm', [TotpController::class, 'confirm'], ['POST']);
            $add('phil_identity_provider_totp_disable', '/account/two-factor/disable', [TotpController::class, 'disable'], ['POST']);
            $add('phil_identity_provider_challenge', '/two-factor', [TotpController::class, 'challenge'], ['GET']);
            $add('phil_identity_provider_code_check', '/two-factor/check', $authenticator, ['POST']);
        }

        if ($this->modules->has(Modules::RECOVERY_CODES)) {
            $add('phil_identity_provider_recovery_codes', '/account/two-factor/recovery-codes', [TotpController::class, 'regenerate'], ['POST']);
        }

        if ($this->modules->has(Modules::LOGIN_LINK)) {
            $add('phil_identity_provider_login_link', '/login/link', [LoginLinkController::class, 'request'], ['GET', 'POST']);
            $add(LoginLinkController::CHECK, '/login/link/check', $authenticator, ['GET']);
        }

        if ($this->modules->has(Modules::SMS)) {
            $add(PhoneController::PAGE, '/account/phone', [PhoneController::class, 'page'], ['GET']);
            $add('phil_identity_provider_phone_start', '/account/phone/start', [PhoneController::class, 'start'], ['POST']);
            $add('phil_identity_provider_phone_confirm', '/account/phone/confirm', [PhoneController::class, 'confirm'], ['POST']);
            $add('phil_identity_provider_phone_remove', '/account/phone/remove', [PhoneController::class, 'remove'], ['POST']);
        }

        if ($this->modules->has(Modules::SESSIONS)) {
            $add(SessionController::PAGE, '/account/sessions', [SessionController::class, 'page'], ['GET']);
            $add('phil_identity_provider_sessions_close', '/account/sessions/{id}/close', [SessionController::class, 'close'], ['POST'], ['id' => '[A-Za-z0-9_-]{43}']);
            $add('phil_identity_provider_sessions_close_others', '/account/sessions/close-others', [SessionController::class, 'closeOthers'], ['POST']);
        }

        if ($this->modules->has(Modules::STEP_UP)) {
            $add('phil_identity_provider_reauthenticate', '/reauthenticate', [StepUpController::class, 'page'], ['GET']);
        }

        if ($this->modules->has(Modules::EXTERNAL)) {
            $add(ExternalController::PAGE, '/account/identities', [ExternalController::class, 'page'], ['GET']);
            $add('phil_identity_provider_external_unlink', '/account/identities/{id}/unlink', [ExternalController::class, 'unlink'], ['POST'], ['id' => '[0-9a-f]{64}']);
            $add('phil_identity_provider_external_start', '/login/with/{provider}', [ExternalController::class, 'start'], ['GET'], ['provider' => '[a-z][a-z0-9_.-]{0,63}']);
            $add('phil_identity_provider_external_callback', '/login/with/{provider}/callback', $authenticator, ['GET', 'POST'], ['provider' => '[a-z][a-z0-9_.-]{0,63}']);
        }

        if ($this->modules->has(Modules::OIDC)) {
            $add(MetadataController::DISCOVERY, '/.well-known/openid-configuration', [MetadataController::class, 'discovery'], ['GET']);
            $add(MetadataController::JWKS, '/oidc/jwks', [MetadataController::class, 'jwks'], ['GET']);
            $add(AuthorizeController::AUTHORIZE, '/oidc/authorize', [AuthorizeController::class, 'authorize'], ['GET', 'POST']);
            $add(AuthorizeController::CONSENT, '/oidc/consent', [AuthorizeController::class, 'consent'], ['POST']);
            $add(TokenController::TOKEN, '/oidc/token', [TokenController::class, 'token'], ['POST']);
            $add(TokenController::REVOKE, '/oidc/revoke', [TokenController::class, 'revoke'], ['POST']);
            $add(TokenController::INTROSPECT, '/oidc/introspect', [TokenController::class, 'introspect'], ['POST']);
            $add(UserinfoController::USERINFO, '/oidc/userinfo', [UserinfoController::class, 'userinfo'], ['GET', 'POST']);
            $add(EndSessionController::END_SESSION, '/oidc/logout', [EndSessionController::class, 'endSession'], ['GET', 'POST']);

            if ($this->modules->has(Modules::SESSIONS)) {
                $add('phil_identity_provider_consent_withdraw', '/account/applications/{client}/withdraw', [SessionController::class, 'withdrawConsent'], ['POST'], ['client' => self::ID]);
            }
        }

        return $routes;
    }
}
