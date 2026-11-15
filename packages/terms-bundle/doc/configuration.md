# Configuration

Every option of `phil_terms`, with its default:

```yaml
phil_terms:
    # The DBAL connection of the two tables.
    connection: default

    # Service id of the PSR-20 clock: the bundle never reads the time itself.
    clock: clock

    # The locale whose text a version shows when it has none in the request's locale.
    default_locale: en

    # The locales the administration asks a text for; the default locale alone when empty.
    locales: []

    # The time zone in which the administration reads the moment a version takes effect and shows the moments of the
    # versions; every moment is kept in UTC.
    timezone: UTC

    # Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.
    # To set in an environment without the Toolkit.
    kit_subdirectory: null

    # The policies, by key — a lowercase letter, then lowercase letters, digits, "_" and "-", 64 characters at most.
    policies:
        terms:
            required: true           # a signed-in member must have accepted it to go on
            renew_after_days: null   # 1 to 3650: the acceptance is asked again after so many days
            contribution_licence: false  # each version names the licence of the contributions; one policy at most

    # Service id of the MemberResolver; by default, the user identifier of the security token, which needs
    # stable_identifiers.
    member_resolver: null

    # Declares that the user identifiers of the security token are never email addresses; read with the default
    # resolver only, which the build refuses without it.
    stable_identifiers: false

    # Required: service id of the MemberSource every member is read from, those who never accepted anything included.
    member_source: ~

    # Service id of a TermsNotifier, instead of the one of "notifications".
    notifier: null

    # Sends a signed-in member who has a version to accept to the acceptance page.
    gate:
        enabled: true
        exempt_routes: []            # on top of the bundle's own, whose names start with phil_terms_
        exempt_paths: ['/_wdt', '/_profiler', '/_error']
        api_paths: []                # answered 403 with a problem in JSON, such as /api/
        problem_type: about:blank    # the "type" URI of that problem

    # Keeps the network an acceptance came from, with phil/ip-anonymizer — never the address itself.
    network:
        enabled: false
        ipv4_prefix: 24              # 0 to 32
        ipv6_prefix: 48              # 0 to 128

    pages:
        layout: '@PhilTerms/layout.html.twig'

    # The administration that publishes the versions.
    admin:
        enabled: false
        role: ROLE_ADMIN
        layout: '@PhilTerms/admin/layout.html.twig'

    # The notices of phil/notifications-bundle; the bundle declares both types.
    notifications:
        enabled: false
        published_type: phil_terms.published
        reminder_type: phil_terms.reminder

    # The reminders, on the schedule "phil_terms"; they need a notifier.
    reminders:
        enabled: false
        frequency: 1 week
        days_ahead: 14               # 0 to 365
```

## What fails the build

The container build stops, with the option at fault, on a missing `member_source`, the default resolver without
`stable_identifiers`, a policy key or a locale of the wrong shape, a renewal outside 1 to 3650 days, more than one
policy naming the contribution licence, a prefix or a number of days out of its bounds, `network` without `phil/ip-anonymizer`,
`notifications` without `phil/notifications-bundle`, and `reminders` without a notifier or without
`symfony/scheduler`.

## The member

`member_resolver` names the identifier an acceptance is kept under. The default reads the user identifier of the
security token, which is often an email address: the build refuses it unless `stable_identifiers: true` declares that
the user identifiers never are, and `TokenMemberResolver` still throws a `LogicException` on an identifier shaped like
an email address, should the declaration be wrong.

An application whose user identifier is an email address names a resolver that gives the member's stable identifier
instead, so that no address is kept with an acceptance, and so that the notices of `phil/notifications-bundle`, whose
identifiers hold no `@`, reach the member.

```php
final readonly class MemberId implements MemberResolver
{
    public function __construct(private Security $security) {}

    public function current(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? (string) $user->id : null;
    }
}
```
