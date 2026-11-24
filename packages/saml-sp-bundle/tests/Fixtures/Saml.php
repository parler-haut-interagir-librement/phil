<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Fixtures;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Signature\Signer;
use Phil\SamlSp\Xml\Instant;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\ServiceProviderSettings;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * The identity provider `acme` of the tests, shaped as Microsoft Entra ID, signing with the test key `entra`, and the
 * service provider signing and decrypting with `sp`; the keys are those of the tests of phil/saml-sp, documented in its
 * testing guide. Responses and logout messages are written here and signed by the library's signer.
 */
final class Saml
{
    public const string IDP = 'https://sts.windows.net/00000000-0000-4000-8000-000000000001/';

    public const string SSO = 'https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2';

    public const string IDP_SLO = 'https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2/logout';

    public const string BASE = 'https://app.example.com/saml/acme';

    public const string OBJECT_ID = '5f0c1a52-3c1e-4d8e-9a8f-0c3b0b9d7e11';

    public static function pem(string $file): string
    {
        $pem = file_get_contents(__DIR__.'/keys/'.$file);

        return false === $pem ? throw new RuntimeException('The test key "'.$file.'" is missing.') : $pem;
    }

    public static function pair(string $name): KeyPair
    {
        return new KeyPair(PrivateKey::fromPem(self::pem($name.'.key')), Certificate::fromPem(self::pem($name.'.crt')));
    }

    /**
     * @return array{certificate: string, private_key: string, passphrase: null}
     */
    public static function pemPair(string $name): array
    {
        return ['certificate' => self::pem($name.'.crt'), 'private_key' => self::pem($name.'.key'), 'passphrase' => null];
    }

    public static function metadata(bool $logout = true): string
    {
        return '<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="'.self::IDP.'">'
            .'<md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            .'<md:KeyDescriptor use="signing"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>'.Certificate::fromPem(self::pem('entra.crt'))->base64().'</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>'
            .($logout ? '<md:SingleLogoutService Binding="'.Binding::Redirect->value.'" Location="'.self::IDP_SLO.'"/>' : '')
            .'<md:SingleSignOnService Binding="'.Binding::Redirect->value.'" Location="'.self::SSO.'"/>'
            .'<md:SingleSignOnService Binding="'.Binding::Post->value.'" Location="'.self::SSO.'"/>'
            .'</md:IDPSSODescriptor></md:EntityDescriptor>';
    }

    public static function settings(string $key = 'acme', bool $createAccounts = true, bool $logout = true, Binding $binding = Binding::Redirect, bool $allowUnsolicited = false): IdentityProviderSettings
    {
        return new IdentityProviderSettings(
            $key,
            null,
            self::metadata(),
            attributes: AttributeMap::entraId(),
            groupRoles: ['0f5b7f6e-1111-4c2d-9e3f-000000000010' => 'ROLE_TEACHER', 'Admin' => 'ROLE_ADMIN'],
            allowUnsolicited: $allowUnsolicited,
            binding: $binding,
            singleLogout: $logout,
            createAccounts: $createAccounts,
        );
    }

    /**
     * @param list<IdentityProviderSettings>|null $settings
     */
    public static function providers(ClockInterface $clock, ?array $settings = null, bool $signing = true): IdentityProviders
    {
        return new IdentityProviders(
            $settings ?? [self::settings()],
            new ServiceProviderSettings(null, $signing ? self::pemPair('sp') : null, [self::pemPair('sp')], null, $signing),
            new MetadataLoader(new NoFetcher(), $clock),
            new FixedEndpoints(),
        );
    }

    /**
     * A response of `acme` to the request of this ID — null for an unsolicited one —, its assertion signed.
     *
     * @param string $base the address under which the routes of the identity provider are
     */
    public static function response(?string $requestId, DateTimeImmutable $now, string $nameId = 'ada.lovelace@example.org', string $base = self::BASE): string
    {
        $issued = Instant::format($now);
        $later = Instant::format($now->modify('+5 minutes'));
        $inResponseTo = null === $requestId ? '' : ' InResponseTo="'.$requestId.'"';
        $assertionId = '_'.bin2hex(random_bytes(16));
        $xml = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="_r'.bin2hex(random_bytes(8)).'" Version="2.0" IssueInstant="'.$issued.'" Destination="'.$base.'/acs"'.$inResponseTo.'>'
            .'<Issuer xmlns="urn:oasis:names:tc:SAML:2.0:assertion">'.self::IDP.'</Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            .'<Assertion xmlns="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.$assertionId.'" IssueInstant="'.$issued.'" Version="2.0">'
            .'<Issuer>'.self::IDP.'</Issuer>'
            .'<Subject><NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.$nameId.'</NameID>'
            .'<SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><SubjectConfirmationData'.$inResponseTo.' NotOnOrAfter="'.$later.'" Recipient="'.$base.'/acs"/></SubjectConfirmation></Subject>'
            .'<Conditions NotBefore="'.$issued.'" NotOnOrAfter="'.$later.'"><AudienceRestriction><Audience>'.$base.'/metadata</Audience></AudienceRestriction></Conditions>'
            .'<AttributeStatement>'
            .'<Attribute Name="http://schemas.microsoft.com/identity/claims/objectidentifier"><AttributeValue>'.self::OBJECT_ID.'</AttributeValue></Attribute>'
            .'<Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress"><AttributeValue>'.$nameId.'</AttributeValue></Attribute>'
            .'<Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname"><AttributeValue>Ada</AttributeValue></Attribute>'
            .'<Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname"><AttributeValue>Lovelace</AttributeValue></Attribute>'
            .'<Attribute Name="http://schemas.microsoft.com/ws/2008/06/identity/claims/groups"><AttributeValue>0f5b7f6e-1111-4c2d-9e3f-000000000010</AttributeValue><AttributeValue>other</AttributeValue></Attribute>'
            .'<Attribute Name="http://schemas.microsoft.com/ws/2008/06/identity/claims/role"><AttributeValue>Admin</AttributeValue></Attribute>'
            .'</AttributeStatement>'
            .'<AuthnStatement AuthnInstant="'.$issued.'" SessionIndex="'.$assertionId.'"><AuthnContext><AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</AuthnContextClassRef></AuthnContext></AuthnStatement>'
            .'</Assertion></samlp:Response>';
        $document = self::load($xml);
        $assertion = $document->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
        \assert($assertion instanceof DOMElement);
        new Signer(self::pair('entra'))->sign($assertion);

        return base64_encode((string) $document->saveXML());
    }

    /**
     * A logout request of `acme` for this person, as XML, signed inside when asked.
     */
    public static function logoutRequest(DateTimeImmutable $now, string $nameId = 'ada.lovelace@example.org', bool $sign = true): string
    {
        $document = self::load('<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_logout'.bin2hex(random_bytes(8)).'" Version="2.0" IssueInstant="'.Instant::format($now).'" Destination="'.self::BASE.'/slo"><saml:Issuer>'.self::IDP.'</saml:Issuer><saml:NameID>'.$nameId.'</saml:NameID><samlp:SessionIndex>_s</samlp:SessionIndex></samlp:LogoutRequest>');

        if ($sign) {
            new Signer(self::pair('entra'))->sign($document->documentElement ?? throw new RuntimeException());
        }

        return (string) $document->saveXML();
    }

    /**
     * A logout response of `acme` to the request of this ID, as XML.
     */
    public static function logoutResponse(DateTimeImmutable $now, string $requestId, string $status = 'urn:oasis:names:tc:SAML:2.0:status:Success'): string
    {
        return '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_lr'.bin2hex(random_bytes(8)).'" Version="2.0" IssueInstant="'.Instant::format($now).'" Destination="'.self::BASE.'/slo" InResponseTo="'.$requestId.'"><saml:Issuer>'.self::IDP.'</saml:Issuer><samlp:Status><samlp:StatusCode Value="'.$status.'"/></samlp:Status></samlp:LogoutResponse>';
    }

    /**
     * The value of a parameter of a query.
     */
    public static function query(string $url, string $name): ?string
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $fields);

        return \is_string($fields[$name] ?? null) ? $fields[$name] : null;
    }

    /**
     * The XML of the message a redirect carries.
     */
    public static function inflate(string $url, string $name = 'SAMLRequest'): string
    {
        return (string) gzinflate((string) base64_decode((string) self::query($url, $name), true));
    }

    public static function load(string $xml): DOMDocument
    {
        $document = new DOMDocument();

        if ('' === $xml || !$document->loadXML($xml)) {
            throw new RuntimeException('The XML is not well-formed.');
        }

        return $document;
    }
}
