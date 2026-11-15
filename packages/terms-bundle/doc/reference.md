# Reference

| Type | Role |
|---|---|
| `PhilTermsBundle` | Configures `phil_terms` |
| `Domain\Identifiers` | The shapes of policy keys, member identifiers, locales and licences |
| `Domain\TextFormat` | Plain text or UJO (Universal JSON Output) |
| `Domain\AcceptanceChannel` | Signup, form, API, import |
| `Domain\ValueObject\Policy` | A declared policy, whether it is required, its renewal, whether it names the contribution licence |
| `Domain\ValueObject\PolicyVersion` | A published version, never changed, with its contribution licence if any |
| `Domain\ValueObject\Acceptance` | A member's acceptance of a version |
| `Domain\Contract\PolicyVersionRepository` | The port of the versions |
| `Domain\Contract\AcceptanceRepository` | The port of the acceptances |
| `Domain\Exception\InvalidTerms` | A value or an operation refused, with its reason |
| `Application\Policies` | The declared policies, in order |
| `Application\VersionCatalog` | The versions of each policy for the request |
| `Application\Terms` | Publishes a version, records an acceptance |
| `Application\AcceptanceChecker` | What a member has to accept |
| `Application\Outstanding` | The members not in order with a policy |
| `Application\ContributionLicences` | The licence of a member's contribution at a moment |
| `Application\ContributionLicence` | A licence, the version accepted and its moment |
| `Application\Contract\MemberResolver` | The port of the current member |
| `Application\Contract\MemberSource` | The port of every member |
| `Application\Contract\TermsNotifier` | The port of the notices and reminders |
| `Application\Contract\AddressMasker` | The port of what is kept of an address |
| `Application\Contract\PolicyTexts` | The port of the texts: checked, shown, compared |
| `Application\Event\VersionPublished`, `TermsAccepted` | The events, identifiers only |
| `Application\Message\SendTermsReminders` | The scheduled run of the reminders |
| `Application\Handler\SendTermsRemindersHandler` | Sends the reminders |
| `Infrastructure\DependencyInjection\TermsServices` | Wires the services of the configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the recipes of the kit after the components subdirectory of the Toolkit |
| `Infrastructure\Doctrine\TermsTables` | The two tables |
| `Infrastructure\Doctrine\TermsSchemaListener` | Adds them to the schema of the ORM |
| `Infrastructure\Doctrine\DbalPolicyVersions`, `DbalAcceptances` | The two stores with DBAL, their moments in UTC |
| `Infrastructure\Doctrine\RowReader` | The values of a row, read strictly |
| `Infrastructure\Http\AcceptanceGate` | Stops a member who has a version to accept |
| `Infrastructure\Http\AcceptanceController` | The acceptance page |
| `Infrastructure\Http\PolicyController` | The public pages and the changes |
| `Infrastructure\Http\TermsAdminController` | The administration |
| `Infrastructure\Http\Pages` | The templates, the token, the address to come back to |
| `Infrastructure\Form\TermsAcceptanceTypeExtension` | The `terms_acceptance` option of a form |
| `Infrastructure\Form\SignupAcceptances` | Keeps the acceptances of a signup form |
| `Infrastructure\Console\OutstandingCommand` | `phil:terms:outstanding` |
| `Infrastructure\Console\LegalLinksCommand` | `phil:terms:check-legal-links` |
| `Infrastructure\Scheduler\TermsSchedule` | The schedule `phil_terms` |
| `Infrastructure\Notifications\NotificationsNotifier` | The notices through `phil/notifications-bundle` |
| `Infrastructure\Notifications\OutstandingAudience` | The audience `phil_terms:<policy>` |
| `Infrastructure\Privacy\AcceptancesPrivacy` | The export and the erasure of `phil/privacy-bundle` |
| `Infrastructure\Security\TokenMemberResolver` | The member of the security token, with `stable_identifiers` declared; never an email address |
| `Infrastructure\IpAnonymizer\NetworkMasker` | The network of an address |
| `Infrastructure\Text\PolicyTextRenderer` | The texts as HTML and their changes |
| `Infrastructure\Twig\TermsTwig` | `phil_terms_kit()`, the name of a recipe of the kit, for the templates |
| `Infrastructure\Twig\KitComponents` | The names under which the recipes of the kit are installed |
| `Infrastructure\EasyAdmin\TermsAdmin` | The link of an EasyAdmin menu |
