<?php

declare(strict_types=1);

namespace Phil\SamlSp\Binding;

use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Signature\SignatureAlgorithm;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Xml\Base64;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\Url\Url;

/**
 * The HTTP-Redirect binding: the message deflated, in base64, in the query; its signature over the query itself,
 * `SAMLRequest=…&RelayState=…&SigAlg=…` as sent, never re-encoded. Reading inflates within the budget of a message, so
 * that a small query cannot expand into a large document.
 */
final class HttpRedirect
{
    public const int MAX_RELAY_STATE = 1024;

    private const array PARAMETERS = ['SAMLRequest', 'SAMLResponse', 'RelayState', 'SigAlg', 'Signature'];

    private function __construct()
    {
    }

    /**
     * The address of the destination with the message in its query, signed when a key is given.
     */
    public static function url(Url $destination, string $parameter, string $xml, ?string $relayState = null, ?PrivateKey $key = null, SignatureAlgorithm $algorithm = SignatureAlgorithm::RsaSha256): string
    {
        $query = $parameter.'='.urlencode(Base64::encode((string) gzdeflate($xml, 9)));
        $query .= null === $relayState ? '' : '&RelayState='.urlencode($relayState);

        if ($key instanceof PrivateKey) {
            $query .= '&SigAlg='.urlencode($algorithm->value);

            if (!openssl_sign($query, $signature, $key->key(), $algorithm->openssl()) || !\is_string($signature)) {
                throw InvalidKey::because('The private key cannot sign.');
            }

            $query .= '&Signature='.urlencode(Base64::encode($signature));
        }

        return $destination->toString().(null === $destination->query ? '?' : '&').$query;
    }

    /**
     * The message of a query, its signature verified when it carries one.
     *
     * @param list<Certificate> $certificates the certificates of the identity provider
     */
    public static function receive(string $query, array $certificates, SignaturePolicy $policy = new SignaturePolicy(), XmlBudget $budget = new XmlBudget()): ReceivedMessage
    {
        $raw = self::parameters($query);
        $parameter = isset($raw['SAMLRequest']) ? 'SAMLRequest' : 'SAMLResponse';

        if (isset($raw['SAMLRequest'], $raw['SAMLResponse']) || !isset($raw[$parameter])) {
            throw InvalidMessage::because('A redirected message carries SAMLRequest or SAMLResponse.');
        }

        $relayState = isset($raw['RelayState']) ? urldecode($raw['RelayState']) : null;

        if (null !== $relayState && \strlen($relayState) > self::MAX_RELAY_STATE) {
            throw InvalidMessage::because(\sprintf('A relay state has %d bytes at most.', self::MAX_RELAY_STATE));
        }

        $verified = isset($raw['Signature']) && self::verify($raw, $parameter, $certificates, $policy);

        return new ReceivedMessage(self::inflate(urldecode($raw[$parameter]), $budget), Binding::Redirect, $relayState, $verified, $parameter);
    }

    /**
     * @return array<string, string> the raw values, still URL-encoded
     */
    private static function parameters(string $query): array
    {
        $raw = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);

            if (!\in_array($name, self::PARAMETERS, true)) {
                continue;
            }

            if (isset($raw[$name])) {
                throw InvalidMessage::because(\sprintf('A redirected message carries %s once.', $name));
            }

            $raw[$name] = $value;
        }

        return $raw;
    }

    /**
     * @param array<string, string> $raw
     * @param list<Certificate>     $certificates
     */
    private static function verify(array $raw, string $parameter, array $certificates, SignaturePolicy $policy): bool
    {
        $algorithm = SignatureAlgorithm::fromUri(urldecode($raw['SigAlg'] ?? ''), $policy);
        $signature = Base64::decode(urldecode($raw['Signature'] ?? ''));
        $signed = $parameter.'='.$raw[$parameter].(isset($raw['RelayState']) ? '&RelayState='.$raw['RelayState'] : '').'&SigAlg='.$raw['SigAlg'];

        if (null === $signature) {
            throw InvalidSignature::because('The signature of the query is not base64.');
        }

        foreach ($certificates as $certificate) {
            if ($certificate->rsaBits() < $policy->minRsaBits) {
                throw InvalidSignature::because(\sprintf('A trusted certificate holds an RSA key shorter than %d bits.', $policy->minRsaBits));
            }

            if (1 === openssl_verify($signed, $signature, $certificate->publicKey(), $algorithm->openssl())) {
                return true;
            }
        }

        throw InvalidSignature::because('The signature of the query does not verify with the certificates of the identity provider.');
    }

    private static function inflate(string $base64, XmlBudget $budget): string
    {
        $deflated = Base64::decode($base64);
        $xml = null === $deflated || \strlen($deflated) > $budget->maxBytes ? false : @gzinflate($deflated, $budget->maxBytes);

        if (false === $xml || '' === $xml) {
            throw InvalidMessage::because(\sprintf('A redirected message is deflated base64 of %d bytes at most.', $budget->maxBytes));
        }

        return $xml;
    }
}
