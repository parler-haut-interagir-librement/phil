<?php

declare(strict_types=1);

namespace Phil\SamlSp\Binding;

use InvalidArgumentException;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Message\OutgoingMessage;
use Phil\SamlSp\Signature\SignatureAlgorithm;
use Phil\SamlSp\Signature\Signer;
use Phil\SamlSp\Xml\XmlWriter;

/**
 * Puts an outgoing message on its binding: in the signed query of HTTP-Redirect, or signed inside and in a form on
 * HTTP-POST.
 */
final class MessageSender
{
    private function __construct()
    {
    }

    public static function send(OutgoingMessage $message, ?KeyPair $signing, ?string $relayState = null, SignatureAlgorithm $algorithm = SignatureAlgorithm::RsaSha256): Outbound
    {
        if ($message->signed && !$signing instanceof KeyPair) {
            throw new InvalidArgumentException('The message is signed: the service provider needs its signing key pair.');
        }

        $keys = $message->signed ? $signing : null;

        if (Binding::Redirect === $message->binding) {
            return new Outbound(Binding::Redirect, HttpRedirect::url($message->destination, $message->parameter, XmlWriter::xml($message->root), $relayState, $keys?->privateKey, $algorithm));
        }

        if ($keys instanceof KeyPair) {
            new Signer($keys, $algorithm)->sign($message->root);
        }

        return HttpPost::form($message->destination, $message->parameter, XmlWriter::xml($message->root), $relayState);
    }
}
