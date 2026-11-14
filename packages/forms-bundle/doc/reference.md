# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilFormsBundle` | `final class`, `AbstractBundle` | Alias `phil_forms`; `NOTIFIER_TAG` |
| `Domain\Model\Form`, `Entry` | entities | A form; an entry |
| `Domain\ValueObject\Field` | value | A field: its type, condition, tiers, disqualifying choices, capacities, sensitivity, the ages of a birth date; `Field::age()` |
| `Domain\ValueObject\Notification` | value | An email sent of each entry under a condition |
| `Domain\Model\ChoiceCount` | entity | The places taken in a choice, for the schema |
| `Domain\Contract\ConditionEvaluator`, `FieldSealer`, `ChoicePlaces` | interfaces | Conditions; sealing sensitive values; places in choices |
| `Domain\Logic\BasicConditions` | `final readonly class` | The conditions `==`, `!=` and `in` on one field |
| `Domain\Field\FieldType` | enum | What a field takes |
| `Domain\Repository\FormRepository` | interface | Saving, finding, listing, what has expired, the entries about a person |
| `Domain\Subject\EmailDigester` | interface | The keyed digest of an address |
| `Domain\Exception\FormsException`, `InvalidForm`, `InvalidEntry`, `UnreadableValue`, `UnsealedSensitiveField` | exceptions | Everything the bundle throws; `UnsealedSensitiveField` for a form with a sensitive field and no sealer |
| `Application\Submissions` | `final readonly class` | Takes an entry and its places, tells the notifiers |
| `Application\SealedValues` | `final readonly class` | The values of an entry, sealed ones opened |
| `Application\Retention` | `final readonly class` | Deletes what has expired |
| `Application\HmacEmailDigester` | `final readonly class` | HMAC-SHA256 of the address normalized |
| `Application\CurrentSubject` | interface | The identifier of the person sending a form |
| `Application\EntryNotifier`, `NotificationFailed` | interface, exception | Telling someone of an entry |
| `Infrastructure\Doctrine\DoctrineFormRepository` | adapter | Doctrine ORM; the places in choices |
| `Infrastructure\KeyManagement\KeyManagementFieldSealer` | adapter | Sealing by the envelope encryption of Symfony's KeyManagement component |
| `Infrastructure\DependencyInjection\FormsServices`, `FieldSealerPass` | wiring | The services of the configuration; the sealer when `sealing.key` names a master key |
| `Infrastructure\Form\EntryType` | form type | The Symfony form of a definition; with `phil/form-logic`, its conditional rows named and its view's `phil_form_logic_steps` |
| `Infrastructure\Form\LogicSteps` | `final readonly class` | The conditions of the fields a page shows, as the steps of `phil/form-logic`'s Stimulus controller |
| `Infrastructure\Http\FormController` | controller | The page of a form |
| `Infrastructure\Mailer\EmailNotifier` | notifier | Email |
| `Infrastructure\Webhook\WebhookNotifier` | notifier | A signed webhook |
| `Infrastructure\Console\PurgeEntriesCommand` | console command | `phil:forms:purge` |
| `Infrastructure\Security\TokenCurrentSubject` | adapter | The user signed in through Symfony Security |
| `Infrastructure\Privacy\FormEntriesPrivacy` | `phil/privacy-bundle` module | Exports and erases the entries about a person |
