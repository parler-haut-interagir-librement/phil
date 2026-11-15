<!-- phil-dev source-sha256=4e28f95ce256f84c38ee319a407be8fa6d7c868c6f0ffe6bb25168280bb02aa0 -->
# Configuration

Chaque option de `phil_terms`, avec sa valeur par défaut :

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

## Ce qui fait échouer la construction

La construction du conteneur s'arrête, en nommant l'option fautive, sur un `member_source` manquant, le résolveur par
défaut sans `stable_identifiers`, une clé de politique ou une locale de forme incorrecte, un renouvellement hors de
l'intervalle de 1 à 3650 jours, plus d'une politique qui nomme la licence des contributions, un préfixe ou un nombre de
jours hors de ses bornes, `network` sans `phil/ip-anonymizer`, `notifications` sans `phil/notifications-bundle`, et
`reminders` sans notificateur ou sans `symfony/scheduler`.

## Le membre

`member_resolver` nomme l'identifiant sous lequel une acceptation est conservée. Le résolveur par défaut lit
l'identifiant d'utilisateur du jeton de sécurité, qui est souvent une adresse courriel : la construction le refuse
à moins que `stable_identifiers: true` ne déclare que ces identifiants n'en sont jamais, et `TokenMemberResolver` lance
quand même une `LogicException` sur un identifiant qui a la forme d'une adresse courriel, si la déclaration est fausse.

Une application dont l'identifiant d'utilisateur est une adresse courriel nomme plutôt un résolveur qui donne
l'identifiant stable du membre : aucune adresse n'est ainsi conservée avec une acceptation, et les avis de
`phil/notifications-bundle`, dont les identifiants ne contiennent aucun `@`, parviennent au membre.

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
