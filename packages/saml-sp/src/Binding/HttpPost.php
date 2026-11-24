<?php

declare(strict_types=1);

namespace Phil\SamlSp\Binding;

use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Xml\Base64;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\Url\Url;

/**
 * The HTTP-POST binding: the message in base64 in a form field, its signature inside the document.
 */
final class HttpPost
{
    private function __construct()
    {
    }

    public static function form(Url $destination, string $parameter, string $xml, ?string $relayState = null): Outbound
    {
        $fields = [$parameter => Base64::encode($xml)];

        if (null !== $relayState) {
            $fields['RelayState'] = $relayState;
        }

        return new Outbound(Binding::Post, $destination->toString(), $fields);
    }

    public static function receive(string $encoded, ?string $relayState = null, XmlBudget $budget = new XmlBudget(), string $parameter = 'SAMLResponse'): ReceivedMessage
    {
        if (null !== $relayState && \strlen($relayState) > HttpRedirect::MAX_RELAY_STATE) {
            throw InvalidMessage::because(\sprintf('A relay state has %d bytes at most.', HttpRedirect::MAX_RELAY_STATE));
        }

        return new ReceivedMessage(self::decode($encoded, $budget), Binding::Post, $relayState, false, $parameter);
    }

    /**
     * The XML of a posted field: base64, spaces and line breaks allowed, within the bytes of the budget.
     */
    public static function decode(string $encoded, XmlBudget $budget = new XmlBudget()): string
    {
        $xml = \strlen($encoded) > intdiv($budget->maxBytes * 4, 3) + 4096 ? null : Base64::decode($encoded);

        if (null === $xml || \strlen($xml) > $budget->maxBytes) {
            throw InvalidMessage::because(\sprintf('A posted message is base64 of %d bytes at most.', $budget->maxBytes));
        }

        return $xml;
    }
}
