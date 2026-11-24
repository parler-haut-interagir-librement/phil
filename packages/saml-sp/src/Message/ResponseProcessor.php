<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Binding\HttpPost;
use Phil\SamlSp\Contract\ReplayGuard;
use Phil\SamlSp\Encryption\Decrypter;
use Phil\SamlSp\Exception\IdentityProviderRefused;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Exception\ReplayedAssertion;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Signature\SignatureVerifier;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\IdMap;
use Phil\SamlSp\Xml\Instant;
use Phil\SamlSp\Xml\Ns;
use Phil\SamlSp\Xml\SafeXml;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;

/**
 * Reads the response an identity provider posts to the assertion consumer service, and returns the person it signs in,
 * or refuses it. See the security guide of the package for every check; in short:
 *
 * - the document is read by `SafeXml`, within the budget of the policy; its IDs are unique, and it holds exactly one
 *   assertion, plain or encrypted, a direct child of the response — never one in `Advice`, an extension or a signature;
 * - a signature present must verify, on the element it references; the assertion must be signed itself unless the
 *   identity provider is configured to sign the response only; what is read is read from the verified elements;
 * - the status is success; the issuer is the identity provider; the destination, the recipient and the audience are
 *   the service provider; the request answered is the one the browser sent, or none for an unsolicited response the
 *   identity provider is allowed to send; the times hold within the clock skew;
 * - an encrypted assertion is decrypted with the keys of the service provider; an assertion is accepted once.
 */
final readonly class ResponseProcessor
{
    public function __construct(
        private ClockInterface $clock,
        private ReplayGuard $replayGuard,
        private SecurityPolicy $policy = new SecurityPolicy(),
    ) {
    }

    /**
     * @param string      $samlResponse the `SAMLResponse` field as posted, in base64
     * @param string|null $requestId    the ID of the pending request it answers; null for an unsolicited response
     */
    public function process(string $samlResponse, IdentityProvider $idp, ServiceProvider $sp, ?string $requestId): SamlIdentity
    {
        $document = SafeXml::load(HttpPost::decode($samlResponse, $this->policy->budget), $this->policy->budget);
        $response = $this->root($document);
        $window = TimeWindow::of($this->clock->now(), $this->policy);
        $verifier = new SignatureVerifier($this->policy->signatures);
        $certificates = $idp->metadata->signingCertificates;
        $responseSigned = SignatureVerifier::hasSignature($response);

        $this->checkResponse($response, $idp, $sp, $requestId, $responseSigned, $window);

        if ($responseSigned) {
            $verifier->verify($response, $certificates);
        }

        $assertion = $this->assertion($response, $idp, $sp);

        if (SignatureVerifier::hasSignature($assertion)) {
            $verifier->verify($assertion, $certificates);
        } elseif ($idp->wantAssertionsSigned || !$responseSigned) {
            throw InvalidSignature::because('The assertion is not signed.');
        }

        return $this->identity($assertion, $idp, $sp, $requestId, $window);
    }

    private function root(DOMDocument $document): DOMElement
    {
        $response = $document->documentElement;

        if (!$response instanceof DOMElement || !Elements::is($response, Ns::PROTOCOL, 'Response') || '2.0' !== Elements::attribute($response, 'Version')) {
            throw InvalidMessage::because('The message is not a SAML 2.0 response.');
        }

        IdMap::of($document);
        $assertions = Elements::countInDocument($response, Ns::ASSERTION, 'Assertion') + Elements::countInDocument($response, Ns::ASSERTION, 'EncryptedAssertion');
        $children = \count(Elements::all($response, Ns::ASSERTION, 'Assertion')) + \count(Elements::all($response, Ns::ASSERTION, 'EncryptedAssertion'));

        if (1 !== $assertions || 1 !== $children) {
            throw InvalidMessage::because('A response holds exactly one assertion, as its own child.');
        }

        return $response;
    }

    private function checkResponse(DOMElement $response, IdentityProvider $idp, ServiceProvider $sp, ?string $requestId, bool $signed, TimeWindow $window): void
    {
        $this->checkStatus($response);
        $issuer = Elements::one($response, Ns::ASSERTION, 'Issuer');

        if ($issuer instanceof DOMElement) {
            $this->checkIssuer($issuer, $idp, 'The response');
        }

        $destination = Elements::attribute($response, 'Destination');

        if ((null === $destination && $signed) || (null !== $destination && !$this->sameUrl($destination, $sp->assertionConsumerService))) {
            throw InvalidMessage::because('The destination of the response is not the assertion consumer service.');
        }

        $this->checkInResponseTo(Elements::attribute($response, 'InResponseTo'), $requestId, 'The response');

        if (null === $requestId && !$idp->allowUnsolicited) {
            throw InvalidMessage::because('The response answers no request of this browser, and the identity provider may not send one unsolicited.');
        }

        $window->checkIssued(Elements::attribute($response, 'IssueInstant'), 'The response');
    }

    private function assertion(DOMElement $response, IdentityProvider $idp, ServiceProvider $sp): DOMElement
    {
        $encrypted = Elements::one($response, Ns::ASSERTION, 'EncryptedAssertion');

        if (!$encrypted instanceof DOMElement) {
            if ($idp->wantAssertionsEncrypted) {
                throw InvalidMessage::because('The assertion is not encrypted.');
            }

            return Elements::one($response, Ns::ASSERTION, 'Assertion') ?? throw InvalidMessage::because('The response holds no assertion.');
        }

        if ([] === $sp->encryption) {
            throw InvalidMessage::because('The assertion is encrypted, and the service provider has no decryption key.');
        }

        $plain = Decrypter::decrypt($encrypted, array_map(static fn (KeyPair $pair): PrivateKey => $pair->privateKey, $sp->encryption));

        return $this->decrypted($plain);
    }

    /**
     * Reads the decrypted assertion in a document of its own, inside an element that declares the usual prefixes, which
     * the identity provider may have declared on the response only.
     */
    private function decrypted(string $plain): DOMElement
    {
        $plain = (string) preg_replace('/^\s*<\?xml[^>]*\?>/', '', $plain);
        $wrapper = '<phil-saml-sp xmlns:saml="'.Ns::ASSERTION.'" xmlns:saml2="'.Ns::ASSERTION.'" xmlns:ds="'.Ns::DSIG.'" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="'.Ns::XSI.'">'.$plain.'</phil-saml-sp>';
        $document = SafeXml::load($wrapper, $this->policy->budget);
        $root = $document->documentElement ?? throw InvalidMessage::because('An encrypted assertion holds exactly one assertion.');
        $children = [];

        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        if (1 !== \count($children) || !Elements::is($children[0], Ns::ASSERTION, 'Assertion') || 1 !== Elements::countInDocument($children[0], Ns::ASSERTION, 'Assertion')) {
            throw InvalidMessage::because('An encrypted assertion holds exactly one assertion.');
        }

        IdMap::of($document);

        return $children[0];
    }

    private function identity(DOMElement $assertion, IdentityProvider $idp, ServiceProvider $sp, ?string $requestId, TimeWindow $window): SamlIdentity
    {
        $id = Elements::attribute($assertion, 'ID');

        if ('2.0' !== Elements::attribute($assertion, 'Version') || null === $id || '' === $id) {
            throw InvalidMessage::because('The assertion is not a SAML 2.0 assertion with an ID.');
        }

        $window->checkIssued(Elements::attribute($assertion, 'IssueInstant'), 'The assertion');
        $this->checkIssuer(Elements::one($assertion, Ns::ASSERTION, 'Issuer') ?? throw InvalidMessage::because('The assertion has one issuer.'), $idp, 'The assertion');
        $subject = Elements::one($assertion, Ns::ASSERTION, 'Subject') ?? throw InvalidMessage::because('The assertion has one subject.');
        $confirmedUntil = $this->confirmation($subject, $sp, $requestId, $window);
        $conditionsUntil = $this->conditions($assertion, $sp, $window);
        [$authnInstant, $sessionIndex, $sessionEnd, $context] = $this->authnStatement($assertion, $window);
        $nameId = Elements::one($subject, Ns::ASSERTION, 'NameID') ?? throw InvalidMessage::because('The subject has one name identifier, not encrypted.');
        $name = Elements::text($nameId);

        if ('' === $name || \strlen($name) > 1024) {
            throw InvalidMessage::because('The name identifier has 1 to 1024 bytes.');
        }

        $until = max($confirmedUntil, $conditionsUntil);

        if (!$this->replayGuard->firstUse($idp->metadata->entityId, $id, $until)) {
            throw ReplayedAssertion::because('The assertion was already accepted once.');
        }

        $attributes = $this->attributes($assertion);

        return new SamlIdentity($idp->metadata->entityId, $name, Elements::attribute($nameId, 'Format'), $sessionIndex, $authnInstant, $sessionEnd, $context, $id, $attributes, $idp->attributes->map($attributes));
    }

    private function confirmation(DOMElement $subject, ServiceProvider $sp, ?string $requestId, TimeWindow $window): DateTimeImmutable
    {
        foreach (Elements::all($subject, Ns::ASSERTION, 'SubjectConfirmation') as $confirmation) {
            $data = Elements::one($confirmation, Ns::ASSERTION, 'SubjectConfirmationData');

            if (Ns::BEARER !== Elements::attribute($confirmation, 'Method') || !$data instanceof DOMElement) {
                continue;
            }

            $recipient = Elements::attribute($data, 'Recipient');

            if (null === $recipient || !$this->sameUrl($recipient, $sp->assertionConsumerService)) {
                throw InvalidMessage::because('The recipient of the assertion is not the assertion consumer service.');
            }

            $this->checkInResponseTo(Elements::attribute($data, 'InResponseTo'), $requestId, 'The subject confirmation');
            $window->checkNotBefore(Elements::attribute($data, 'NotBefore'), 'The subject confirmation');

            return $window->checkNotOnOrAfter(Elements::attribute($data, 'NotOnOrAfter') ?? throw InvalidMessage::because('The subject confirmation has a NotOnOrAfter.'), 'The subject confirmation');
        }

        throw InvalidMessage::because('The subject has no bearer confirmation.');
    }

    private function conditions(DOMElement $assertion, ServiceProvider $sp, TimeWindow $window): DateTimeImmutable
    {
        $conditions = Elements::one($assertion, Ns::ASSERTION, 'Conditions') ?? throw InvalidMessage::because('The assertion has one Conditions.');
        $window->checkNotBefore(Elements::attribute($conditions, 'NotBefore'), 'The assertion');
        $until = $window->checkNotOnOrAfter(Elements::attribute($conditions, 'NotOnOrAfter') ?? throw InvalidMessage::because('The conditions of the assertion have a NotOnOrAfter.'), 'The assertion');
        $restrictions = Elements::all($conditions, Ns::ASSERTION, 'AudienceRestriction');

        if ([] === $restrictions) {
            throw InvalidMessage::because('The assertion is restricted to an audience.');
        }

        foreach ($restrictions as $restriction) {
            $audiences = array_map(Elements::text(...), Elements::all($restriction, Ns::ASSERTION, 'Audience'));

            if (!\in_array($sp->entityId, $audiences, true)) {
                throw InvalidMessage::because('The service provider is not an audience of the assertion.');
            }
        }

        return $until;
    }

    /**
     * @return array{DateTimeImmutable, string|null, DateTimeImmutable|null, string|null}
     */
    private function authnStatement(DOMElement $assertion, TimeWindow $window): array
    {
        $statement = Elements::all($assertion, Ns::ASSERTION, 'AuthnStatement')[0] ?? throw InvalidMessage::because('The assertion has an authentication statement.');
        $instant = Instant::parse((string) Elements::attribute($statement, 'AuthnInstant'), 'The authentication instant');
        $end = Elements::attribute($statement, 'SessionNotOnOrAfter');
        $context = Elements::one($statement, Ns::ASSERTION, 'AuthnContext');
        $class = $context instanceof DOMElement ? Elements::one($context, Ns::ASSERTION, 'AuthnContextClassRef') : null;

        return [$instant, Elements::attribute($statement, 'SessionIndex'), null === $end ? null : $window->checkNotOnOrAfter($end, 'The session of the identity provider'), $class instanceof DOMElement ? Elements::text($class) : null];
    }

    /**
     * @return array<string, list<string>>
     */
    private function attributes(DOMElement $assertion): array
    {
        $attributes = [];

        foreach (Elements::all($assertion, Ns::ASSERTION, 'AttributeStatement') as $statement) {
            foreach (Elements::all($statement, Ns::ASSERTION, 'Attribute') as $attribute) {
                $name = (string) Elements::attribute($attribute, 'Name');

                foreach (Elements::all($attribute, Ns::ASSERTION, 'AttributeValue') as $value) {
                    if ('' !== $name && 'true' !== $value->getAttributeNS(Ns::XSI, 'nil')) {
                        $attributes[$name][] = Elements::text($value);
                    }
                }
            }
        }

        return $attributes;
    }

    private function checkStatus(DOMElement $response): void
    {
        $status = Elements::one($response, Ns::PROTOCOL, 'Status');
        $code = $status instanceof DOMElement ? Elements::one($status, Ns::PROTOCOL, 'StatusCode') : null;
        $value = $code instanceof DOMElement ? Elements::attribute($code, 'Value') : null;

        if (!$code instanceof DOMElement || null === $value) {
            throw InvalidMessage::because('The response has one status code.');
        }

        if (Ns::STATUS_SUCCESS !== $value) {
            $second = Elements::one($code, Ns::PROTOCOL, 'StatusCode');

            throw IdentityProviderRefused::withStatus($value, $second instanceof DOMElement ? Elements::attribute($second, 'Value') : null);
        }
    }

    private function checkIssuer(DOMElement $issuer, IdentityProvider $idp, string $what): void
    {
        $format = Elements::attribute($issuer, 'Format');

        if ((null !== $format && Ns::ENTITY !== $format) || Elements::text($issuer) !== $idp->metadata->entityId) {
            throw InvalidMessage::because(\sprintf('%s is not issued by the identity provider.', $what));
        }
    }

    private function checkInResponseTo(?string $inResponseTo, ?string $requestId, string $what): void
    {
        if (null === $inResponseTo && null === $requestId) {
            return;
        }

        if (null === $inResponseTo || null === $requestId || !hash_equals($requestId, $inResponseTo)) {
            throw InvalidMessage::because(\sprintf('%s does not answer the request of this browser.', $what));
        }
    }

    private function sameUrl(string $text, Url $expected): bool
    {
        try {
            return Url::fromString($text, true)->equals($expected);
        } catch (UrlException) {
            return false;
        }
    }
}
