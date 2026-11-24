<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use DateTimeImmutable;
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Contract\ReplayGuard;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Metadata\IdentityProviderMetadata;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Store\InMemoryReplayGuard;
use Phil\Url\Url;
use Symfony\Component\Clock\MockClock;

/**
 * The service provider and the two identity providers of the fixtures, at the instant the fixtures were issued.
 */
final class Stage
{
    public const string NOW = '2026-11-19T12:00:00Z';

    public const string SP = 'https://sp.example.org/saml/metadata';

    public const string ACS = 'https://sp.example.org/saml/acme/acs';

    public const string SLO = 'https://sp.example.org/saml/acme/slo';

    public const string REQUEST = '_4fee3b046395c4e751011e97f8900b5273d56685';

    public const string ENTRA = 'https://sts.windows.net/00000000-0000-4000-8000-000000000001/';

    public const string OKTA = 'http://www.okta.com/exk00000000000000001';

    public static function clock(string $at = '2026-11-19 12:01:00 UTC'): MockClock
    {
        return new MockClock(new DateTimeImmutable($at));
    }

    public static function sp(bool $encryption = true): ServiceProvider
    {
        return new ServiceProvider(self::SP, Url::fromString(self::ACS), Url::fromString(self::SLO), Keys::pair('sp'), $encryption ? [Keys::pair('sp')] : []);
    }

    public static function entra(bool $wantAssertionsSigned = true, bool $allowUnsolicited = false, bool $wantAssertionsEncrypted = false): IdentityProvider
    {
        return new IdentityProvider(
            new IdentityProviderMetadata(
                self::ENTRA,
                [Binding::Redirect->value => Url::fromString('https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2'), Binding::Post->value => Url::fromString('https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2')],
                [Binding::Redirect->value => Url::fromString('https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2')],
                [Keys::certificate('entra')],
            ),
            AttributeMap::entraId(),
            $wantAssertionsSigned,
            $wantAssertionsEncrypted,
            $allowUnsolicited,
        );
    }

    public static function okta(): IdentityProvider
    {
        return new IdentityProvider(
            new IdentityProviderMetadata(
                self::OKTA,
                [Binding::Post->value => Url::fromString('https://example.okta.com/app/example_sp_1/exk00000000000000001/sso/saml')],
                [Binding::Post->value => Url::fromString('https://example.okta.com/app/example_sp_1/exk00000000000000001/slo/saml')],
                [Keys::certificate('okta')],
            ),
            AttributeMap::okta(),
            requestBinding: Binding::Post,
        );
    }

    public static function processor(?MockClock $clock = null, ?ReplayGuard $guard = null, SecurityPolicy $policy = new SecurityPolicy()): ResponseProcessor
    {
        $clock ??= self::clock();

        return new ResponseProcessor($clock, $guard ?? new InMemoryReplayGuard($clock), $policy);
    }

    public static function fixture(string $name): string
    {
        return base64_encode(Keys::fixture($name));
    }
}
