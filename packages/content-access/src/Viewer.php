<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use Phil\ContentAccess\Exception\InvalidViewer;

/**
 * Who asks to read a content: a stable identifier when signed in (never an email address), the roles the application
 * gave, the domain of an email address the application verified, and the network address of the request. The library
 * keeps none of it beyond the decision it takes.
 */
final readonly class Viewer
{
    /** Lowercase ASCII labels of a host name, the internationalized ones in their `xn--` form. */
    public const string DOMAIN_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D';

    public const string ROLE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,127}$/D';

    public const int MAX_ROLES = 100;

    /** @var list<string> */
    public array $roles;

    public ?string $verifiedEmailDomain;

    /**
     * @param list<string> $roles
     *
     * @throws InvalidViewer when the identifier is empty, a role is not an identifier, the domain is not a host name or
     *                       the address is not an IP address
     */
    private function __construct(
        public ?string $id,
        array $roles,
        ?string $verifiedEmailDomain,
        public ?string $ip,
    ) {
        if (null !== $id && ('' === $id || \strlen($id) > 255 || 1 === preg_match('/[\x00-\x1F\x7F]/', $id))) {
            throw InvalidViewer::field('identifier', 'is empty, longer than 255 bytes or holds a control character');
        }

        if (\count($roles) > self::MAX_ROLES) {
            throw InvalidViewer::field('roles', \sprintf('are more than %d', self::MAX_ROLES));
        }

        foreach ($roles as $role) {
            if (1 !== preg_match(self::ROLE_PATTERN, $role)) {
                throw InvalidViewer::field('role', 'is not an identifier');
            }
        }

        $this->roles = array_values(array_unique($roles));
        $this->verifiedEmailDomain = null === $verifiedEmailDomain ? null : self::domain($verifiedEmailDomain);

        if (null !== $ip && false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            throw InvalidViewer::field('address', 'is not an IP address');
        }
    }

    /**
     * A visitor who is not signed in. The address, when given, lets an institution's network open a content.
     */
    public static function anonymous(?string $ip = null): self
    {
        return new self(null, [], null, $ip);
    }

    /**
     * A signed-in member. Pass the email domain only once the application verified the address.
     *
     * @param list<string> $roles
     */
    public static function member(string $id, array $roles = [], ?string $verifiedEmailDomain = null, ?string $ip = null): self
    {
        return new self($id, $roles, $verifiedEmailDomain, $ip);
    }

    public function isSignedIn(): bool
    {
        return null !== $this->id;
    }

    /**
     * The domain in its canonical form: lowercase, without a leading `@` or a trailing dot.
     *
     * @throws InvalidViewer when it is not a host name
     */
    public static function domain(string $domain): string
    {
        $canonical = rtrim(ltrim(strtolower(trim($domain)), '@'), '.');

        if (1 !== preg_match(self::DOMAIN_PATTERN, $canonical)) {
            throw InvalidViewer::field('email domain', 'is not a host name');
        }

        return $canonical;
    }
}
