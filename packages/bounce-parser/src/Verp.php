<?php

declare(strict_types=1);

namespace Phil\BounceParser;

use Phil\BounceParser\Exception\InvalidVerp;
use SensitiveParameter;

/**
 * A variable envelope return path: each message is sent with its own return path, `bounces+TAG@example.com`, so that
 * the address its bounce reaches names it, whatever the bounce says. The tag is the application's: an identifier it
 * signs with `encodeSigned()`, or the recipient written `ada=example.com` as classic VERP does.
 *
 * Anyone can mail a return path, so a tag the application acts on — a bounce counted against a recipient, a
 * suppression — must be one only it can write: a signed tag, checked by `decodeSigned()`. A classic VERP tag names the
 * recipient in clear and anyone can write one; it is not enough to decide a suppression.
 */
final readonly class Verp
{
    private string $prefix;

    private string $domain;

    /**
     * @param string $localPart the part before the separator, `bounces`
     * @param string $domain    the domain of the bounce mailbox
     *
     * @throws InvalidVerp when a part is empty, or the separator is not one character an address can hold
     */
    public function __construct(string $localPart, string $domain, string $separator = '+')
    {
        if ('' === $localPart || '' === $domain || 1 !== preg_match('/^[+\-=_.]$/', $separator) || str_contains($localPart, $separator)) {
            throw new InvalidVerp('A return path needs a part before the separator, a domain and a separator among + - = _ . that the part does not hold.');
        }

        $this->prefix = strtolower($localPart).$separator;
        $this->domain = strtolower($domain);
    }

    /**
     * The return path of a message, its tag written as it is.
     *
     * @throws InvalidVerp when the tag holds a character an address cannot hold
     */
    public function encode(string $tag): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~.-]+$/', $tag) || str_contains($tag, '..')) {
            throw new InvalidVerp('A tag holds only the characters of an atom, without two dots in a row.');
        }

        return $this->prefix.$tag.'@'.$this->domain;
    }

    /**
     * The return path of a message with a signed tag, `bounces+ID.MAC@example.com`: the identifier and an HMAC-SHA256
     * of the identifier and of this mailbox under the key, cut to 128 bits.
     *
     * @param string $key the application's secret, at least 16 bytes
     *
     * @throws InvalidVerp when the key is short or the identifier is empty or holds a character an address cannot hold
     */
    public function encodeSigned(string $id, #[SensitiveParameter] string $key): string
    {
        $this->encode($id);

        return $this->encode($id.'.'.$this->signature($id, $key));
    }

    /**
     * The identifier of a signed tag — `BounceReport::$verpTag` — or `null` when the tag is not one this mailbox signed
     * under the key.
     *
     * @param string $key the key the tag was signed with
     *
     * @throws InvalidVerp when the key is short
     */
    public function decodeSigned(?string $tag, #[SensitiveParameter] string $key): ?string
    {
        $text = (string) $tag;
        $dot = strrpos($text, '.');
        $id = false === $dot ? '' : substr($text, 0, $dot);
        $mac = false === $dot ? '' : substr($text, $dot + 1);

        return hash_equals($this->signature($id, $key), strtolower($mac)) && '' !== $id ? $id : null;
    }

    /**
     * The return path of a message to a recipient, classic VERP: `ada@example.com` written `ada=example.com`. Anyone
     * can write such a tag: it does not prove a bounce is about the recipient it names, and must not decide a
     * suppression — `encodeSigned()` does.
     *
     * @throws InvalidVerp when the address holds a character an address cannot hold
     */
    public function encodeRecipient(string $address): string
    {
        $at = strrpos($address, '@');

        return $this->encode(false === $at ? $address : substr($address, 0, $at).'='.substr($address, $at + 1));
    }

    /**
     * The tag of a return path of this mailbox, `<bounces+TAG@example.com>` or with a display name, or `null` when the
     * address is not one.
     */
    public function decode(string $address): ?string
    {
        $text = trim(1 === preg_match('/<([^<>]*)>/', $address, $match) ? $match[1] : $address);
        $at = strrpos($text, '@');

        if (false === $at || strtolower(substr($text, $at + 1)) !== $this->domain) {
            return null;
        }

        $local = substr($text, 0, $at);
        $prefixLength = \strlen($this->prefix);

        if (\strlen($local) <= $prefixLength || 0 !== strncasecmp($local, $this->prefix, $prefixLength)) {
            return null;
        }

        return substr($local, $prefixLength);
    }

    /**
     * The recipient a classic VERP return path names, or `null`.
     */
    public function decodeRecipient(string $address): ?string
    {
        $tag = (string) $this->decode($address);
        $equals = strrpos($tag, '=');

        return false === $equals ? null : substr($tag, 0, $equals).'@'.substr($tag, $equals + 1);
    }

    /**
     * @throws InvalidVerp when the key is short
     */
    private function signature(string $id, string $key): string
    {
        if (\strlen($key) < 16) {
            throw new InvalidVerp('A signing key holds at least 16 bytes.');
        }

        return substr(hash_hmac('sha256', $this->prefix.$id.'@'.$this->domain, $key), 0, 32);
    }
}
