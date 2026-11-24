<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Builds the responses of the fixtures, unsigned, shaped as the two identity providers write them: Microsoft Entra ID
 * with default namespaces and the claim URIs, Okta with the `saml2` prefixes, an `Issuer` format and typed attribute
 * values whose `xs` prefix only an `InclusiveNamespaces` list keeps. Every part a test changes is an option.
 */
final class Responses
{
    public const string PROTOCOL = 'urn:oasis:names:tc:SAML:2.0:protocol';

    public const string ASSERTION = 'urn:oasis:names:tc:SAML:2.0:assertion';

    private const array DEFAULTS = [
        'responseId' => '_c7055387-af61-4fce-8b98-e2927324b306',
        'assertionId' => '_9a6c6f9b-2f1e-4a43-9d36-7fd3e5f4b200',
        'issueInstant' => '2026-11-19T12:00:00.123Z',
        'notBefore' => '2026-11-19T11:55:00.123Z',
        'notOnOrAfter' => '2026-11-19T13:10:00.123Z',
        'confirmationNotOnOrAfter' => '2026-11-19T12:05:00.123Z',
        'destination' => Stage::ACS,
        'recipient' => Stage::ACS,
        'audience' => Stage::SP,
        'inResponseTo' => Stage::REQUEST,
        'nameId' => 'ada.lovelace@example.org',
        'issuer' => null,
        'status' => 'urn:oasis:names:tc:SAML:2.0:status:Success',
        'sessionNotOnOrAfter' => null,
    ];

    /**
     * @param array<string, string|null> $options
     */
    public static function entra(array $options = []): DOMDocument
    {
        $o = self::options($options, Stage::ENTRA);
        $inResponseTo = null === $o['inResponseTo'] ? '' : \sprintf(' InResponseTo="%s"', $o['inResponseTo']);
        $session = null === $o['sessionNotOnOrAfter'] ? '' : \sprintf(' SessionNotOnOrAfter="%s"', $o['sessionNotOnOrAfter']);
        $claims = [
            'http://schemas.microsoft.com/identity/claims/tenantid' => ['00000000-0000-4000-8000-000000000001'],
            'http://schemas.microsoft.com/identity/claims/objectidentifier' => ['5f0c1a52-3c1e-4d8e-9a8f-0c3b0b9d7e11'],
            'http://schemas.microsoft.com/identity/claims/displayname' => ['Ada Lovelace'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => ['Ada'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname' => ['Lovelace'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['ada.lovelace@example.org'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => ['ada.lovelace@example.org'],
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups' => ['0f5b7f6e-1111-4c2d-9e3f-000000000010', '0f5b7f6e-1111-4c2d-9e3f-000000000020'],
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/role' => ['Teacher'],
        ];
        $attributes = '';

        foreach ($claims as $name => $values) {
            $attributes .= '<Attribute Name="'.$name.'">'.implode('', array_map(static fn (string $value): string => '<AttributeValue>'.$value.'</AttributeValue>', $values)).'</Attribute>';
        }

        return self::load(
            '<samlp:Response ID="'.$o['responseId'].'" Version="2.0" IssueInstant="'.$o['issueInstant'].'" Destination="'.$o['destination'].'"'.$inResponseTo.' xmlns:samlp="'.self::PROTOCOL.'">'
            .'<Issuer xmlns="'.self::ASSERTION.'">'.$o['issuer'].'</Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="'.$o['status'].'"/></samlp:Status>'
            .'<Assertion ID="'.$o['assertionId'].'" IssueInstant="'.$o['issueInstant'].'" Version="2.0" xmlns="'.self::ASSERTION.'">'
            .'<Issuer>'.$o['issuer'].'</Issuer>'
            .'<Subject><NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.$o['nameId'].'</NameID>'
            .'<SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><SubjectConfirmationData'.$inResponseTo.' NotOnOrAfter="'.$o['confirmationNotOnOrAfter'].'" Recipient="'.$o['recipient'].'"/></SubjectConfirmation></Subject>'
            .'<Conditions NotBefore="'.$o['notBefore'].'" NotOnOrAfter="'.$o['notOnOrAfter'].'"><AudienceRestriction><Audience>'.$o['audience'].'</Audience></AudienceRestriction></Conditions>'
            .'<AttributeStatement>'.$attributes.'</AttributeStatement>'
            .'<AuthnStatement AuthnInstant="2026-11-19T11:59:58.000Z" SessionIndex="_9a6c6f9b-2f1e-4a43-9d36-7fd3e5f4b200"'.$session.'><AuthnContext><AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</AuthnContextClassRef></AuthnContext></AuthnStatement>'
            .'</Assertion></samlp:Response>',
        );
    }

    /**
     * @param array<string, string|null> $options
     */
    public static function okta(array $options = []): DOMDocument
    {
        $o = self::options($options + ['responseId' => 'id4512094846321471937011235', 'assertionId' => 'id45120948463858251254399876'], Stage::OKTA);
        $inResponseTo = null === $o['inResponseTo'] ? '' : \sprintf(' InResponseTo="%s"', $o['inResponseTo']);
        $attributes = '';

        foreach (['user_id' => ['00u1a2b3c4d5e6f7g8h9'], 'email' => ['ada.lovelace@example.org'], 'firstName' => ['Ada'], 'lastName' => ['Lovelace'], 'groups' => ['Everyone', 'Teachers']] as $name => $values) {
            $attributes .= '<saml2:Attribute Name="'.$name.'" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:unspecified">'
                .implode('', array_map(static fn (string $value): string => '<saml2:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">'.$value.'</saml2:AttributeValue>', $values))
                .'</saml2:Attribute>';
        }

        $entity = ' Format="urn:oasis:names:tc:SAML:2.0:nameid-format:entity"';

        return self::load(
            '<saml2p:Response xmlns:saml2p="'.self::PROTOCOL.'" Destination="'.$o['destination'].'" ID="'.$o['responseId'].'"'.$inResponseTo.' IssueInstant="'.$o['issueInstant'].'" Version="2.0">'
            .'<saml2:Issuer xmlns:saml2="'.self::ASSERTION.'"'.$entity.'>'.$o['issuer'].'</saml2:Issuer>'
            .'<saml2p:Status xmlns:saml2p="'.self::PROTOCOL.'"><saml2p:StatusCode Value="'.$o['status'].'"/></saml2p:Status>'
            .'<saml2:Assertion xmlns:saml2="'.self::ASSERTION.'" ID="'.$o['assertionId'].'" IssueInstant="'.$o['issueInstant'].'" Version="2.0" xmlns:xs="http://www.w3.org/2001/XMLSchema">'
            .'<saml2:Issuer'.$entity.'>'.$o['issuer'].'</saml2:Issuer>'
            .'<saml2:Subject><saml2:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">'.$o['nameId'].'</saml2:NameID>'
            .'<saml2:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml2:SubjectConfirmationData'.$inResponseTo.' NotOnOrAfter="'.$o['confirmationNotOnOrAfter'].'" Recipient="'.$o['recipient'].'"/></saml2:SubjectConfirmation></saml2:Subject>'
            .'<saml2:Conditions NotBefore="'.$o['notBefore'].'" NotOnOrAfter="'.$o['notOnOrAfter'].'"><saml2:AudienceRestriction><saml2:Audience>'.$o['audience'].'</saml2:Audience></saml2:AudienceRestriction></saml2:Conditions>'
            .'<saml2:AuthnStatement AuthnInstant="2026-11-19T11:59:57.551Z" SessionIndex="id1763553597551.1874093281"><saml2:AuthnContext><saml2:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml2:AuthnContextClassRef></saml2:AuthnContext></saml2:AuthnStatement>'
            .'<saml2:AttributeStatement>'.$attributes.'</saml2:AttributeStatement>'
            .'</saml2:Assertion></saml2p:Response>',
        );
    }

    public static function assertion(DOMDocument $document): DOMElement
    {
        $assertion = $document->getElementsByTagNameNS(self::ASSERTION, 'Assertion')->item(0);

        return $assertion instanceof DOMElement ? $assertion : throw new RuntimeException('The response holds no assertion.');
    }

    public static function root(DOMDocument $document): DOMElement
    {
        return $document->documentElement ?? throw new RuntimeException('The document is empty.');
    }

    public static function first(DOMDocument $document, string $query): DOMElement
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('samlp', self::PROTOCOL);
        $xpath->registerNamespace('saml', self::ASSERTION);
        $xpath->registerNamespace('ds', TestSigner::DSIG);

        $found = $xpath->query($query);
        $node = false === $found ? null : $found->item(0);

        return $node instanceof DOMElement ? $node : throw new RuntimeException(\sprintf('Nothing matches "%s".', $query));
    }

    /**
     * @param array<string, string|null> $options
     *
     * @return array<string, string|null>
     */
    private static function options(array $options, string $issuer): array
    {
        $o = $options + self::DEFAULTS;
        $o['issuer'] ??= $issuer;

        return $o;
    }

    private static function load(string $xml): DOMDocument
    {
        return Dom::load($xml);
    }
}
