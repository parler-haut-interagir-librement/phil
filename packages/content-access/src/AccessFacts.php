<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use DateTimeImmutable;
use Phil\ContentAccess\Entitlement\Entitlements;
use Phil\ContentAccess\Exception\ReservedFact;
use Phil\Rules\Fact\Facts;

/**
 * The facts a rule of access reads: the viewer, the grants and the instant of the decision, under names the library
 * sets, plus the application's own facts, such as a lesson completed or a profile field. The grants are read from
 * their source only when a condition asks for them, and never for a visitor who is not signed in.
 */
final class AccessFacts
{
    /** The viewer's stable identifier, or null. */
    public const string VIEWER_ID = 'viewer.id';

    /** The viewer's roles, a list of identifiers. */
    public const string VIEWER_ROLES = 'viewer.roles';

    /** The verified email domain of the viewer, or null. */
    public const string VIEWER_EMAIL_DOMAIN = 'viewer.email_domain';

    /** The network address of the request, or null. */
    public const string VIEWER_IP = 'viewer.ip';

    /** The viewer's grants, a list of `Grant`. */
    public const string VIEWER_GRANTS = 'viewer.grants';

    /** The instant of the decision, a `DateTimeImmutable`. */
    public const string NOW = 'now';

    public const array RESERVED = [self::VIEWER_ID, self::VIEWER_ROLES, self::VIEWER_EMAIL_DOMAIN, self::VIEWER_IP, self::VIEWER_GRANTS, self::NOW];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $extra the application's facts, a value or a closure without parameters each
     *
     * @throws ReservedFact when an extra fact takes a name the library sets
     */
    public static function for(Viewer $viewer, Entitlements $entitlements, DateTimeImmutable $now, array $extra = []): Facts
    {
        foreach (self::RESERVED as $name) {
            if (\array_key_exists($name, $extra)) {
                throw ReservedFact::named($name);
            }
        }

        $id = $viewer->id;
        $grants = null === $id ? [] : static fn (): array => $entitlements->grantsOf($id);

        return Facts::of([
            self::VIEWER_ID => $id,
            self::VIEWER_ROLES => $viewer->roles,
            self::VIEWER_EMAIL_DOMAIN => $viewer->verifiedEmailDomain,
            self::VIEWER_IP => $viewer->ip,
            self::VIEWER_GRANTS => $grants,
            self::NOW => $now,
            ...$extra,
        ]);
    }
}
