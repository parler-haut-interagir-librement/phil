<?php

declare(strict_types=1);

namespace Phil\InstantMessenger;

use Override;
use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneFormat;
use Phil\Phone\PhoneNumber;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * The international messaging services, and the handles each one knows: a phone number, a user name, an ID, an email
 * address. Each reads its own links too: `https://t.me/ada` is the Telegram handle `ada`.
 *
 * A service is here when it is used across countries and names its people itself, or by a phone number or an email
 * address; services bound to a workspace or a server, social networks, and regional services are left to the
 * application's own enum.
 */
enum KnownMessenger: string implements MessengerType
{
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Signal = 'signal';
    case Messenger = 'messenger';
    case Matrix = 'matrix';
    case Xmpp = 'xmpp';
    case Threema = 'threema';
    case Viber = 'viber';
    case Line = 'line';
    case Discord = 'discord';
    case WeChat = 'wechat';
    case IMessage = 'imessage';
    case Snapchat = 'snapchat';
    case Session = 'session';

    /**
     * The first segments of `t.me` links that are pages of Telegram's own — an invitation, a sticker set, a proxy —,
     * never a user name: Telegram reserves them.
     */
    private const array TELEGRAM_PAGES = ['joinchat', 'addstickers', 'addemoji', 'addtheme', 'addlist', 'share', 'proxy', 'socks', 'setlanguage', 'login', 'confirmphone', 'invoice', 'boost', 'giftcode', 'contact'];

    #[Override]
    public function normalize(string $input): ?string
    {
        $input = trim($input);

        // A link is read only by the services that publish one.
        return match ($this) {
            self::WhatsApp => self::phone($this->fromLink($input) ?? $input),
            self::Viber => self::phone($input),
            self::Signal => $this->signal($input),
            self::Telegram => self::matched('/^[a-z][a-z0-9_]{3,30}[a-z0-9]$/', strtolower(ltrim($this->fromLink($input) ?? $input, '@'))),
            self::Messenger => self::matched('/^[a-z0-9.]{5,50}$/', strtolower($this->fromLink($input) ?? $input)),
            self::Matrix => self::matched('/^@[a-z0-9._=\/+-]+:[a-z0-9.-]+\.[a-z]{2,}(:\d{1,5})?$/', strtolower($this->fromLink($input) ?? $input)),
            // A localpart without the characters RFC 7622 forbids, nor control characters, nor `?` and `#`, which an
            // `xmpp:` link would read as its query and fragment.
            self::Xmpp => self::matched('/^[^\s\x00-\x1F\x7F@\/"&\'<>:?#]{1,64}@[a-z0-9.-]+\.[a-z]{2,}$/', strtolower(preg_replace('/^xmpp:/i', '', $input) ?? $input)),
            self::Threema => self::matched('/^[A-Z0-9*][A-Z0-9]{7}$/', strtoupper($this->fromLink($input) ?? $input)),
            self::Line => $this->line($input),
            self::Discord => 1 === preg_match('/\.\./', $input) ? null : self::matched('/^[a-z0-9_.]{2,32}$/', strtolower(ltrim($input, '@'))),
            self::WeChat => self::matched('/^[a-zA-Z][a-zA-Z0-9_-]{5,19}$/', $input),
            self::IMessage => str_contains($input, '@') ? self::matched('/^[a-z0-9._%+-]{1,64}@[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/', strtolower($input)) : self::phone($input),
            self::Snapchat => self::matched('/^[a-z][a-z0-9._-]{1,13}[a-z0-9]$/', strtolower(ltrim($this->fromLink($input) ?? $input, '@'))),
            self::Session => self::matched('/^05[0-9a-f]{64}$/', strtolower($input)),
        };
    }

    #[Override]
    public function display(string $handle): string
    {
        return match ($this) {
            self::Telegram, self::Discord => '@'.$handle,
            default => str_starts_with($handle, '+') ? self::readable($handle) : $handle,
        };
    }

    #[Override]
    public function link(string $handle): ?string
    {
        return match ($this) {
            self::WhatsApp => 'https://wa.me/'.ltrim($handle, '+'),
            self::Telegram => 'https://t.me/'.$handle,
            self::Signal => str_starts_with($handle, '+') ? 'https://signal.me/#p/'.$handle : null,
            self::Messenger => 'https://m.me/'.$handle,
            self::Matrix => 'https://matrix.to/#/'.$handle,
            self::Xmpp => 'xmpp:'.self::xmppNode($handle),
            self::Threema => 'https://threema.id/'.$handle,
            self::Viber => 'viber://chat?number='.rawurlencode($handle),
            self::Line => str_starts_with($handle, '@') ? 'https://line.me/R/ti/p/'.$handle : 'https://line.me/ti/p/~'.$handle,
            self::Snapchat => 'https://www.snapchat.com/add/'.$handle,
            self::Discord, self::WeChat, self::IMessage, self::Session => null,
        };
    }

    /**
     * The service's own name, which is not translated.
     */
    public function label(): string
    {
        return match ($this) {
            self::Xmpp => 'XMPP',
            self::IMessage => 'iMessage',
            default => $this->name,
        };
    }

    /**
     * The handle a link of this service carries, or `null` when the input is no such link.
     */
    private function fromLink(string $input): ?string
    {
        // A user name or a number is no address: without a dot in its host, it is refused as one.
        try {
            $url = Url::fromString(str_contains($input, '://') ? $input : 'https://'.$input);
        } catch (UrlException) {
            return null;
        }

        $first = $url->segments()[0] ?? null;

        return match ($this) {
            self::WhatsApp => match (true) {
                $url->isOn('wa.me') => null === $first ? null : '+'.$first,
                $url->isOn('whatsapp.com') => self::queryValue($url, 'phone', '+'),
                default => null,
            },
            self::Telegram => $url->isOn('t.me', 'telegram.me') && !\in_array(strtolower($first ?? ''), self::TELEGRAM_PAGES, true) ? $first : null,
            self::Signal => $url->isOn('signal.me') && null !== $url->fragment ? preg_replace('#^p/#', '', $url->fragment) : null,
            self::Messenger => $url->isOn('m.me') ? $first : null,
            self::Matrix => $url->isOn('matrix.to') && null !== $url->fragment ? ltrim($url->fragment, '/') : null,
            self::Threema => $url->isOn('threema.id') ? $first : null,
            self::Line => $url->isOn('line.me') && 1 === preg_match('#^/(?:R/)?ti/p/([~@][^/]+)/?$#i', rawurldecode($url->path), $matches) ? $matches[1] : null,
            self::Snapchat => $url->isOn('snapchat.com') && 'add' === $first ? $url->segments()[1] ?? null : null,
            self::Viber, self::Xmpp, self::Discord, self::WeChat, self::IMessage, self::Session => null,
        };
    }

    /**
     * A Signal phone number, from a `signal.me` link or written with `+`, or a Signal user name.
     */
    private function signal(string $input): ?string
    {
        $fromLink = $this->fromLink($input);

        return null !== $fromLink || str_starts_with($input, '+')
            ? self::phone($fromLink ?? $input)
            : self::matched('/^[a-z][a-z0-9_]{2,31}\.\d{2,9}$/', strtolower($input));
    }

    /**
     * A LINE ID, typed bare or after `~`, or an official account's ID, which LINE writes after `@` and which keeps it:
     * the two open different pages.
     */
    private function line(string $input): ?string
    {
        $handle = strtolower($this->fromLink($input) ?? $input);

        return str_starts_with($handle, '@')
            ? self::matched('/^@[a-z0-9._-]{4,20}$/', $handle)
            : self::matched('/^[a-z0-9._-]{4,20}$/', ltrim($handle, '~'));
    }

    /**
     * The handle as the path of an `xmpp:` URI, RFC 5122: the localpart percent-encoded but for the characters a node
     * may carry as they are.
     */
    private static function xmppNode(string $handle): string
    {
        $at = (int) strrpos($handle, '@');

        return strtr(rawurlencode(substr($handle, 0, $at)), ['%21' => '!', '%24' => '$', '%28' => '(', '%29' => ')', '%2A' => '*', '%2B' => '+', '%2C' => ',', '%3B' => ';', '%3D' => '=']).substr($handle, $at);
    }

    private static function queryValue(Url $url, string $key, string $prefix): ?string
    {
        parse_str($url->query ?? '', $query);
        $value = $query[$key] ?? null;

        return \is_string($value) ? $prefix.ltrim($value, '+') : null;
    }

    /**
     * A number in its international form, as the services that know people by their number keep it — one that
     * exists in its country's plan, without an extension —, or `null`. A national number names no one abroad: the
     * country code is required.
     */
    private static function phone(string $input): ?string
    {
        if (!str_starts_with($input, '+')) {
            return null;
        }

        try {
            $number = PhoneNumber::parse($input);
        } catch (PhoneException) {
            return null;
        }

        return null === $number->extension && $number->isValid() ? $number->format(PhoneFormat::E164) : null;
    }

    private static function readable(string $handle): string
    {
        try {
            return PhoneNumber::parse($handle)->format(PhoneFormat::International);
        } catch (PhoneException) {
            return $handle;
        }
    }

    private static function matched(string $pattern, string $handle): ?string
    {
        // The input is trimmed: a pattern's `$` has no newline left to forgive.
        return 1 === preg_match($pattern, $handle) ? $handle : null;
    }
}
