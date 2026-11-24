<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use DOMElement;
use Phil\SamlSp\Exception\IdentityProviderRefused;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Signature\SignatureVerifier;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\Ns;
use Phil\SamlSp\Xml\SafeXml;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;

/**
 * Reads the logout messages of an identity provider: its `LogoutRequest`, when the person signs out there, and its
 * `LogoutResponse`, which answers the request of the service provider. Both are signed — by the query of HTTP-Redirect
 * or inside on HTTP-POST —, issued by the identity provider, sent to the logout service of the service provider, and
 * within their times.
 */
final readonly class LogoutProcessor
{
    public function __construct(private ClockInterface $clock, private SecurityPolicy $policy = new SecurityPolicy())
    {
    }

    public function request(ReceivedMessage $message, IdentityProvider $idp, ServiceProvider $sp): LogoutRequest
    {
        $root = $this->read($message, 'LogoutRequest', $idp, $sp);
        $window = TimeWindow::of($this->clock->now(), $this->policy);
        $notOnOrAfter = Elements::attribute($root, 'NotOnOrAfter');

        if (null !== $notOnOrAfter) {
            $window->checkNotOnOrAfter($notOnOrAfter, 'The logout request');
        }

        $nameId = Elements::one($root, Ns::ASSERTION, 'NameID') ?? throw InvalidMessage::because('A logout request has one name identifier, not encrypted.');
        $sessions = array_map(Elements::text(...), Elements::all($root, Ns::PROTOCOL, 'SessionIndex'));

        return new LogoutRequest((string) Elements::attribute($root, 'ID'), $idp->metadata->entityId, Elements::text($nameId), Elements::attribute($nameId, 'Format'), $sessions);
    }

    /**
     * Accepts the logout response that answers the request of this ID, or refuses it.
     */
    public function response(ReceivedMessage $message, IdentityProvider $idp, ServiceProvider $sp, ?string $requestId): void
    {
        $root = $this->read($message, 'LogoutResponse', $idp, $sp);
        $inResponseTo = Elements::attribute($root, 'InResponseTo');

        if (null === $requestId || null === $inResponseTo || !hash_equals($requestId, $inResponseTo)) {
            throw InvalidMessage::because('The logout response does not answer the logout request of this browser.');
        }

        $status = Elements::one($root, Ns::PROTOCOL, 'Status');
        $code = $status instanceof DOMElement ? Elements::one($status, Ns::PROTOCOL, 'StatusCode') : null;
        $value = $code instanceof DOMElement ? Elements::attribute($code, 'Value') : null;

        if (!$code instanceof DOMElement || Ns::STATUS_SUCCESS !== $value) {
            $second = $code instanceof DOMElement ? Elements::one($code, Ns::PROTOCOL, 'StatusCode') : null;

            throw IdentityProviderRefused::withStatus((string) $value, $second instanceof DOMElement ? Elements::attribute($second, 'Value') : null);
        }
    }

    private function read(ReceivedMessage $message, string $name, IdentityProvider $idp, ServiceProvider $sp): DOMElement
    {
        $root = SafeXml::load($message->xml, $this->policy->budget)->documentElement;

        if (!$root instanceof DOMElement || !Elements::is($root, Ns::PROTOCOL, $name) || '2.0' !== Elements::attribute($root, 'Version') || '' === (string) Elements::attribute($root, 'ID')) {
            throw InvalidMessage::because(\sprintf('The message is not a SAML 2.0 %s.', $name));
        }

        if (!$message->signatureVerified) {
            if (!SignatureVerifier::hasSignature($root)) {
                throw InvalidSignature::because(\sprintf('The %s is not signed.', $name));
            }

            new SignatureVerifier($this->policy->signatures)->verify($root, $idp->metadata->signingCertificates);
        }

        $issuer = Elements::one($root, Ns::ASSERTION, 'Issuer');

        if (!$issuer instanceof DOMElement || Elements::text($issuer) !== $idp->metadata->entityId) {
            throw InvalidMessage::because(\sprintf('The %s is not issued by the identity provider.', $name));
        }

        $destination = Elements::attribute($root, 'Destination');

        if (null !== $destination && (!$sp->singleLogoutService instanceof Url || !$this->sameUrl($destination, $sp->singleLogoutService))) {
            throw InvalidMessage::because(\sprintf('The destination of the %s is not the logout service.', $name));
        }

        TimeWindow::of($this->clock->now(), $this->policy)->checkIssued(Elements::attribute($root, 'IssueInstant'), 'The '.$name);

        return $root;
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
