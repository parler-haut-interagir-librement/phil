# Configuration

```yaml
phil_doctrine_behaviours:
    timestamps: true
    blame:
        enabled: true
        user_method: null
    soft_delete:
        enabled: true
        filter: true
    tree: true
    deactivation:
        enabled: true
        filter: true
    owner:
        enabled: false
        user_method: null              # required when enabled
    publication:
        enabled: false
        workflow: phil_publication
        define_workflow: true
    position: true
    translation: true
    slug:
        enabled: false
    versions:
        enabled: false
        restore_right: PHIL_VERSION_RESTORE
    dated_identifier:
        enabled: false
    privacy:
        enabled: true                  # only with phil/privacy-bundle installed
        blame_replacement: null
        owner_replacement: null
```

A behaviour that needs nothing more is on, and one that needs a package or a setting is off until it is asked for. A
shortcut reads `owner: false`, `publication: true`, and so on.

| Option | Default | Role |
|---|---|---|
| `timestamps` | `true` | `TimestampListener` on `prePersist` and `onFlush` |
| `blame` | on | `BlameListener` on `prePersist` and `onFlush`, the `BlameResolver` service and the public `BlameEraser`; `false` switches it off |
| `blame.user_method` | `null` | A public method of the user class that names them in the blame — a UUID getter —; `getUserIdentifier()` when `null` |
| `soft_delete` | on | `SoftDeleteListener` on `onFlush`; `false` switches it off |
| `soft_delete.filter` | `true` | Adds `phil_soft_deleted` to the default entity manager, enabled |
| `tree` | `true` | `TreeListener` on `postPersist`, `onFlush` and `postFlush`, reset with `kernel.reset`, and the public `Tree` service |
| `deactivation` | on | `DeactivationListener` on `prePersist` and `onFlush` |
| `deactivation.filter` | `true` | Adds `phil_deactivated` to the default entity manager, enabled |
| `owner` | off | `OwnerListener` on `prePersist`, the `OwnerResolver` service and the public `OwnerEraser` |
| `owner.user_method` | `null` | A public method of the user class giving a stable identifier; required when `owner` is on |
| `publication` | off | `PublicationListener` on the workflow's `entered` event; needs `symfony/workflow` |
| `publication.workflow` | `phil_publication` | The name of the workflow: lowercase letters, digits, underscores |
| `publication.define_workflow` | `true` | Declares the state machine under `framework.workflows`; `false` when the application declares its own |
| `position` | `true` | `PositionListener` on `onFlush`, and the public `Positions` service |
| `translation` | `true` | `TranslationListener` on `loadClassMetadata`, which adds the unique index of each translation table, and the public `Translations` service |
| `slug` | off | `SlugListener` on `onFlush`, with the `Sluggers` of `phil/slug-bundle`, which must be registered |
| `versions` | off | `VersionListener` on `onFlush`, the public `Versions` service, the `VersionRestoreGuard` and the mapping of `RecordVersion` |
| `versions.restore_right` | `PHIL_VERSION_RESTORE` | The Symfony Security attribute needed to go back to a past version |
| `dated_identifier` | off | `DatedIdentifierListener` on `prePersist`; needs `symfony/uid` |
| `privacy` | on | `BlamePrivacy` with `blame`, `OwnerPrivacy` with `owner`, tagged for `phil/privacy-bundle` when it is installed; `false` switches them off |
| `privacy.blame_replacement` | `null` | The pseudonym that takes an erased name's place in the blame, 1 to 255 bytes; `null` leaves the blame empty |
| `privacy.owner_replacement` | `null` | The owner identifier that takes an erased owner's place, without an `@`; `null` leaves the records without an owner |

The filter is added to `doctrine.orm.filters`, the configuration of a single entity manager. An application with
several entity managers sets `soft_delete.filter: false` and `deactivation.filter: false`, and adds the filters to the
ones that need them:

```yaml
doctrine:
    orm:
        entity_managers:
            default:
                filters:
                    phil_soft_deleted: { class: Phil\DoctrineBehaviours\SoftDeletedFilter, enabled: true }
                    phil_deactivated: { class: Phil\DoctrineBehaviours\DeactivatedFilter, enabled: true }
```

## The blame resolver

With Symfony Security installed, `BlameResolver` is `SecurityBlameResolver`: the identifier of the user of the token,
nobody without one — a command, a job. Without it, nobody is blamed. A user who acts as another through
`switch_user` is blamed for what they do, not the user they impersonate.

The identifier is often an e-mail address, copied into every blamed row and kept after the account is gone: personal
information under privacy laws. `blame.user_method` names the user by a method that gives a stable identifier that is
none, such as a UUID; `BlameEraser` finds and replaces a user's name in every blamed table when they ask for their
data or its erasure.

```yaml
phil_doctrine_behaviours:
    blame:
        user_method: getUuid
```

An application can also replace the service to name someone else:

```yaml
services:
    Phil\DoctrineBehaviours\Contract\BlameResolver: '@App\Blame\CommandBlame'
```

## The owner

`SecurityOwnerResolver` names the user of the token by the method `owner.user_method` gives. No default exists, because
`getUserIdentifier()` is often an e-mail address, and leaving the option out is refused when the container is built. A
value with an `@` is refused by `OwnerId` when it is read. Under `switch_user` the owner is the account acted as, where
blame names the person acting: a record made on someone's behalf belongs to them.

## Privacy requests

With `phil/privacy-bundle` installed, the blame and the owner take part in its access and erasure requests, each when
its behaviour is on, under the module names `behaviours.blame` and `behaviours.owner`. Without it, nothing is
registered.

| Request | Blame | Owner |
|---|---|---|
| access | how many rows name the person, by table, under `rows_by_table` | how many rows the person owns, by table, under `rows_by_table` |
| erasure | the name replaced by `privacy.blame_replacement`, or by nobody, in every blamed table | the records handed to `privacy.owner_replacement`, or to nobody |

The erasure goes through `BlameEraser` and `OwnerEraser`: soft-deleted and filtered rows too, the listeners not run.
Its report names every table of the behaviour, found or not, as `pseudonymised` with a pseudonym and `redacted`
without one, so a second run reports the same; the module itself stands for the store when no table has the behaviour.

The data subject is the identifier the privacy bundle resolves, and the blame and the owner hold what their resolvers
gave. They match when `blame.user_method` and `owner.user_method` name the same identifier as the user's data subject,
such as a UUID. A subject with an `@` owns nothing, since no owner holds one.

## Publication

The bundle declares a state machine named `publication.workflow`, so it is a service of `workflow.registry` like any
other. Guards, listeners and `phil/scheduled-transitions-bundle` reach it by the name of the transition, `publish` or
`unpublish`. With `define_workflow: false` the application declares its own, under the same name, with the places
`draft`, `published` and `unpublished`, and the marking store `method` on the property `publicationState`.

## Positions

`PositionListener` sends a `Positionable` record with no position to the end of its group as it is flushed, and the
public `Positions` service moves a record first, last, before or after another one. The group is what
`positionGroup()` returns: the fields — a column, or a to-one relation held by one column — whose values make a group
ordered on its own, such as a checklist, a parent, a tenant or a locale. An empty list keeps one order for the whole
table.

```php
#[ORM\Entity]
class Step implements Positionable
{
    use PositionableEntity;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 36)]
        public string $checklist,
    ) {
    }

    public function positionGroup(): array
    {
        return ['checklist'];
    }
}
```

```php
$positions->moveBefore($lock, $pack);   // both in the same checklist
$manager->flush();
```

## Slugs and their scope

With `slug` on, `SlugListener` gives each `Sluggable` record a slug from `slugSource()`, made by the slugger
`sluggerName()` names among those of `phil/slug-bundle`, or its default one. A slug is unique in its table with
`SluggableEntity`. With `ScopedSluggableEntity`, it is unique within the fields `slugScope()` returns — a tenant, a
locale, a parent —, and the entity declares the unique index on those columns and the slug:

```php
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'article_locale_slug', columns: ['locale', 'slug'])]
class Article implements Sluggable
{
    use ScopedSluggableEntity;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 16)]
        public string $locale,
        #[ORM\Column]
        public string $title,
    ) {
    }

    public function slugSource(): string
    {
        return $this->title;
    }

    public function slugScope(): array
    {
        return ['locale'];
    }
}
```

Two articles titled “Welcome” get `welcome` in French and in English, and a second French one `welcome-2`.

## Translations

With `translation` on, `TranslationListener` checks each translation entity as its mapping is loaded and gives its
table a unique index on the record and the locale. The public `Translations` service reads a record's row along a
fallback chain of `phil/locale-dimensions`, preloads the rows of a list, and gives the row of one locale to write. A row
is served whole: the chain picks a row, never a field, and nothing is copied from another locale. There is no source
text to compile: each locale is a row of its own, written by whoever translates it.

A translated record keeps what every locale shares; its text lives on a translation entity, with only the translated
columns:

```php
#[ORM\Entity]
class Faq implements Translatable
{
    public function __construct(
        #[ORM\Id, ORM\Column(length: 36)]
        public string $id,
    ) {
    }

    public static function translationClass(): string
    {
        return FaqTranslation::class;
    }

    public static function translatedFields(): array
    {
        return ['question'];
    }

    public function newTranslation(Locale $locale): FaqTranslation
    {
        return new FaqTranslation($this, $locale);
    }
}

#[ORM\Entity]
class FaqTranslation implements Translation
{
    use TranslationEntity;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    public string $question = '';

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Faq::class)]
        #[ORM\JoinColumn(name: 'faq_id', nullable: false, onDelete: 'CASCADE')]
        private Faq $translatable,
        Locale $locale,
    ) {
        $this->writtenIn($locale);
    }

    public function translatable(): Faq
    {
        return $this->translatable;
    }
}
```

```php
$french = $translations->translate($faq, Locale::fromString('fr'));
$french->question = 'Quand ouvrez-vous ?';
$manager->flush();

$row = $translations->find($faq, FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr')]));
$translations->values($row);   // ['question' => 'Quand ouvrez-vous ?']
```

The table `faq_translation` gets the unique index `faq_translation_translatable_locale` on `faq_id` and `locale`. The
fallback chain usually comes from the application's declared locales, `chainFor()` of a `DeclaredLocales`.

## Instants

The timestamps, the soft delete, the deactivation, the publication and the frozen versions take their time from the
`clock` service of Symfony, converted to UTC: the columns hold the UTC instant whatever the time zone of PHP or of the
clock. The bundle writes no instant of its own and maps none but the library's `RecordVersion`: each column has the
type its trait declares in `phil/doctrine-behaviours`. A test clock, `MockClock` or `ClockSensitiveTrait`, sets the
instants a test reads back.

## Versions and the right to go back

`Versions::restore()` asks Symfony Security for `versions.restore_right` with the record as subject, so a voter can
decide record by record. A refusal is an `AccessDeniedException`, the usual 403. Without Security, or without a voter
that grants it, nobody goes back. A command replaces the guard:

```yaml
services:
    Phil\DoctrineBehaviours\Contract\VersionRestoreGuard: '@Phil\DoctrineBehaviours\AllowVersionRestore'
    Phil\DoctrineBehaviours\AllowVersionRestore: ~
```

The table `phil_record_version` is created by a migration of the application, as any mapped entity's.

## Long-running workers

Nothing a listener or resolver holds lasts beyond a call: `TreeListener` alone keeps something during a flush, and it is
reset with `kernel.reset`. The bundle needs no other care under a worker runtime.
