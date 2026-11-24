<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

/**
 * One mechanism of an SPF record, such as `include:_spf.provider.example` or `-all`.
 */
final readonly class SpfMechanism
{
    /**
     * The mechanisms that cost a DNS lookup (RFC 7208, section 4.6.4).
     */
    private const array LOOKUPS = ['include', 'a', 'mx', 'ptr', 'exists'];

    /**
     * @param string  $name   the mechanism in lowercase: all, include, a, mx, ptr, ip4, ip6 or exists
     * @param ?string $target the domain it names, in lowercase without a final dot; null for `all`, `ip4`, `ip6`, and
     *                        for `a`, `mx` and `ptr` that name the current domain. A target holding `%` is a macro.
     * @param string  $value  what follows the name, as written: `:domain/24`, `:192.0.2.0/24`
     */
    public function __construct(
        public Qualifier $qualifier,
        public string $name,
        public ?string $target = null,
        public string $value = '',
    ) {
    }

    public function costsLookup(): bool
    {
        return \in_array($this->name, self::LOOKUPS, true);
    }
}
