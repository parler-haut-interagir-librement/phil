<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use DOMElement;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Xml\Instant;
use Phil\SamlSp\Xml\Ns;
use Phil\SamlSp\Xml\XmlWriter;
use Psr\Clock\ClockInterface;

/**
 * Writes the messages of the service provider: the `AuthnRequest` that sends a person to the identity provider, the
 * `LogoutRequest` that signs them out there, and the `LogoutResponse` that answers a logout of the identity provider.
 * Each ID is 160 random bits; the logout messages are always signed, a request when the service provider signs its
 * requests or the identity provider asks for it.
 */
final readonly class MessageFactory
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public static function newId(): string
    {
        return '_'.sodium_bin2hex(random_bytes(20));
    }

    public function authnRequest(ServiceProvider $sp, IdentityProvider $idp, bool $forceAuthn = false, bool $isPassive = false): OutgoingMessage
    {
        [$binding, $destination] = $idp->metadata->singleSignOn($idp->requestBinding);
        $id = self::newId();
        $root = XmlWriter::document(Ns::PROTOCOL, 'samlp:AuthnRequest');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml', Ns::ASSERTION);
        $this->header($root, $id, $destination->toString());
        $root->setAttribute('AssertionConsumerServiceURL', $sp->assertionConsumerService->toString());
        $root->setAttribute('ProtocolBinding', Binding::Post->value);

        if ($forceAuthn) {
            $root->setAttribute('ForceAuthn', 'true');
        }

        if ($isPassive) {
            $root->setAttribute('IsPassive', 'true');
        }

        XmlWriter::child($root, Ns::ASSERTION, 'saml:Issuer', [], $sp->entityId);
        XmlWriter::child($root, Ns::PROTOCOL, 'samlp:NameIDPolicy', ['Format' => $sp->nameIdFormat, 'AllowCreate' => 'true']);

        return new OutgoingMessage($id, $root, $destination, $binding, 'SAMLRequest', $sp->signRequests || $idp->metadata->wantAuthnRequestsSigned);
    }

    public function logoutRequest(ServiceProvider $sp, IdentityProvider $idp, string $nameId, ?string $nameIdFormat = null, ?string $sessionIndex = null): OutgoingMessage
    {
        [$binding, $destination] = $idp->metadata->singleLogout(Binding::Redirect) ?? throw InvalidMetadata::because('The identity provider has no single logout service.');
        $id = self::newId();
        $root = XmlWriter::document(Ns::PROTOCOL, 'samlp:LogoutRequest');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml', Ns::ASSERTION);
        $this->header($root, $id, $destination->toString());
        XmlWriter::child($root, Ns::ASSERTION, 'saml:Issuer', [], $sp->entityId);
        XmlWriter::child($root, Ns::ASSERTION, 'saml:NameID', ['Format' => $nameIdFormat], $nameId);

        if (null !== $sessionIndex) {
            XmlWriter::child($root, Ns::PROTOCOL, 'samlp:SessionIndex', [], $sessionIndex);
        }

        return new OutgoingMessage($id, $root, $destination, $binding, 'SAMLRequest', true);
    }

    public function logoutResponse(ServiceProvider $sp, IdentityProvider $idp, string $inResponseTo, bool $success = true): OutgoingMessage
    {
        [$binding, $destination] = $idp->metadata->singleLogout(Binding::Redirect) ?? throw InvalidMetadata::because('The identity provider has no single logout service.');
        $id = self::newId();
        $root = XmlWriter::document(Ns::PROTOCOL, 'samlp:LogoutResponse');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml', Ns::ASSERTION);
        $this->header($root, $id, $destination->toString());
        $root->setAttribute('InResponseTo', $inResponseTo);
        XmlWriter::child($root, Ns::ASSERTION, 'saml:Issuer', [], $sp->entityId);
        $status = XmlWriter::child($root, Ns::PROTOCOL, 'samlp:Status');
        XmlWriter::child($status, Ns::PROTOCOL, 'samlp:StatusCode', ['Value' => $success ? Ns::STATUS_SUCCESS : Ns::STATUS_REQUESTER]);

        return new OutgoingMessage($id, $root, $destination, $binding, 'SAMLResponse', true);
    }

    private function header(DOMElement $root, string $id, string $destination): void
    {
        $root->setAttribute('ID', $id);
        $root->setAttribute('Version', '2.0');
        $root->setAttribute('IssueInstant', Instant::format($this->clock->now()));
        $root->setAttribute('Destination', $destination);
    }
}
