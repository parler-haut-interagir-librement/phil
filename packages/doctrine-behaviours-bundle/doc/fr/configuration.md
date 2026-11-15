<!-- phil-dev source-sha256=c256371e13c6d6b14eb9444217b90a25b9f55ea28ccb77a9cd1e3a9726e64d4d -->
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

Un comportement qui n'a besoin de rien de plus est activé, et un comportement qui a besoin d'un paquet ou d'un réglage
est désactivé jusqu'à ce qu'on le demande. Un raccourci s'écrit `owner: false`, `publication: true`, et ainsi de suite.

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `timestamps` | `true` | `TimestampListener` sur `prePersist` et `onFlush` |
| `blame` | activé | `BlameListener` sur `prePersist` et `onFlush`, le service `BlameResolver` et le `BlameEraser` public ; `false` le désactive |
| `blame.user_method` | `null` | Une méthode publique de la classe d'utilisateur qui le nomme dans l'auteur, un accesseur d'UUID ; `getUserIdentifier()` quand elle vaut `null` |
| `soft_delete` | activé | `SoftDeleteListener` sur `onFlush` ; `false` le désactive |
| `soft_delete.filter` | `true` | Ajoute `phil_soft_deleted` au gestionnaire d'entités par défaut, activé |
| `tree` | `true` | `TreeListener` sur `postPersist`, `onFlush` et `postFlush`, réinitialisé avec `kernel.reset`, et le service public `Tree` |
| `deactivation` | activé | `DeactivationListener` sur `prePersist` et `onFlush` |
| `deactivation.filter` | `true` | Ajoute `phil_deactivated` au gestionnaire d'entités par défaut, activé |
| `owner` | désactivé | `OwnerListener` sur `prePersist`, le service `OwnerResolver` et l'`OwnerEraser` public |
| `owner.user_method` | `null` | Une méthode publique de la classe d'utilisateur qui donne un identifiant stable ; requise quand `owner` est activé |
| `publication` | désactivé | `PublicationListener` sur l'événement `entered` du workflow ; exige `symfony/workflow` |
| `publication.workflow` | `phil_publication` | Le nom du workflow : lettres minuscules, chiffres, soulignés |
| `publication.define_workflow` | `true` | Déclare la machine à états sous `framework.workflows` ; `false` quand l'application déclare la sienne |
| `position` | `true` | `PositionListener` sur `onFlush`, et le service public `Positions` |
| `translation` | `true` | `TranslationListener` sur `loadClassMetadata`, qui ajoute l'index unique de chaque table de traduction, et le service public `Translations` |
| `slug` | désactivé | `SlugListener` sur `onFlush`, avec les `Sluggers` de `phil/slug-bundle`, qui doit être enregistré |
| `versions` | désactivé | `VersionListener` sur `onFlush`, le service public `Versions`, le `VersionRestoreGuard` et l'association de `RecordVersion` |
| `versions.restore_right` | `PHIL_VERSION_RESTORE` | L'attribut de Symfony Security nécessaire pour revenir à une version passée |
| `dated_identifier` | désactivé | `DatedIdentifierListener` sur `prePersist` ; exige `symfony/uid` |
| `privacy` | activé | `BlamePrivacy` avec `blame`, `OwnerPrivacy` avec `owner`, étiquetés pour `phil/privacy-bundle` quand il est installé ; `false` les désactive |
| `privacy.blame_replacement` | `null` | Le pseudonyme qui prend la place d'un nom effacé dans l'auteur, de 1 à 255 octets ; `null` laisse l'auteur vide |
| `privacy.owner_replacement` | `null` | L'identifiant de propriétaire qui prend la place d'un propriétaire effacé, sans `@` ; `null` laisse les enregistrements sans propriétaire |

Le filtre est ajouté à `doctrine.orm.filters`, la configuration d'un seul gestionnaire d'entités. Une application avec
plusieurs gestionnaires d'entités définit `soft_delete.filter: false` et `deactivation.filter: false`, et ajoute les
filtres à ceux qui en ont besoin :

```yaml
doctrine:
    orm:
        entity_managers:
            default:
                filters:
                    phil_soft_deleted: { class: Phil\DoctrineBehaviours\SoftDeletedFilter, enabled: true }
                    phil_deactivated: { class: Phil\DoctrineBehaviours\DeactivatedFilter, enabled: true }
```

## Le résolveur d'auteur

Avec Symfony Security installé, `BlameResolver` est `SecurityBlameResolver` : l'identifiant de l'utilisateur du jeton,
personne sans jeton (une commande, une tâche). Sans lui, personne n'est nommé. Un utilisateur qui agit comme un autre
par `switch_user` est nommé pour ce qu'il fait, et non l'utilisateur qu'il incarne.

L'identifiant est souvent une adresse courriel, copiée dans chaque ligne tracée et gardée après la disparition du
compte : un renseignement personnel au sens des lois sur la vie privée. `blame.user_method` nomme l'utilisateur par une
méthode qui donne un identifiant stable qui n'en est pas un, comme un UUID ; `BlameEraser` trouve et remplace le nom
d'un utilisateur dans chaque table tracée quand il demande ses données ou leur effacement.

```yaml
phil_doctrine_behaviours:
    blame:
        user_method: getUuid
```

Une application peut aussi remplacer le service pour nommer quelqu'un d'autre :

```yaml
services:
    Phil\DoctrineBehaviours\Contract\BlameResolver: '@App\Blame\CommandBlame'
```

## Le propriétaire

`SecurityOwnerResolver` nomme l'utilisateur du jeton par la méthode que donne `owner.user_method`. Aucune valeur par
défaut n'existe, parce que `getUserIdentifier()` est souvent une adresse courriel, et omettre l'option est refusé à la
construction du conteneur. Une valeur avec un `@` est refusée par `OwnerId` à sa lecture. Sous `switch_user`, le
propriétaire est le compte incarné, alors que l'auteur nomme la personne qui agit : un enregistrement fait au nom de
quelqu'un lui appartient.

## Les demandes de vie privée

Avec `phil/privacy-bundle` installé, l'auteur et le propriétaire participent à ses demandes d'accès et d'effacement,
chacun quand son comportement est activé, sous les noms de module `behaviours.blame` et `behaviours.owner`. Sans lui,
rien n'est enregistré.

| Demande | Auteur | Propriétaire |
|---|---|---|
| accès | combien de lignes nomment la personne, par table, sous `rows_by_table` | combien de lignes la personne possède, par table, sous `rows_by_table` |
| effacement | le nom remplacé par `privacy.blame_replacement`, ou par personne, dans chaque table tracée | les enregistrements remis à `privacy.owner_replacement`, ou à personne |

L'effacement passe par `BlameEraser` et `OwnerEraser` : les lignes supprimées en douceur et filtrées aussi, sans
exécuter les écouteurs. Son rapport nomme chaque table du comportement, trouvée ou non, comme `pseudonymised` avec un
pseudonyme et `redacted` sans pseudonyme, si bien qu'une deuxième exécution rapporte la même chose ; le module lui-même
tient lieu de stockage quand aucune table n'a le comportement.

La personne concernée est l'identifiant que résout le bundle de vie privée, et l'auteur et le propriétaire contiennent
ce qu'ont donné leurs résolveurs. Ils correspondent quand `blame.user_method` et `owner.user_method` nomment le même
identifiant que la personne concernée de l'utilisateur, comme un UUID. Une personne concernée avec un `@` ne possède
rien, puisqu'aucun propriétaire n'en contient.

## Publication

Le bundle déclare une machine à états nommée `publication.workflow` : c'est donc un service de `workflow.registry`
comme un autre. Les gardes, les écouteurs et `phil/scheduled-transitions-bundle` l'atteignent par le nom de la
transition, `publish` ou `unpublish`. Avec `define_workflow: false`, l'application déclare la sienne, sous le même nom,
avec les places `draft`, `published` et `unpublished`, et le stockage de marquage `method` sur la propriété
`publicationState`.

## Les positions

`PositionListener` envoie un enregistrement `Positionable` sans position à la fin de son groupe lors du *flush*, et le
service public `Positions` déplace un enregistrement en premier, en dernier, avant ou après un autre. Le groupe est ce
que renvoie `positionGroup()` : les champs (une colonne, ou une relation vers un seul élément portée par une seule
colonne) dont les valeurs forment un groupe ordonné à part, comme une liste de vérification, un parent, un locataire ou
une locale. Une liste vide garde un seul ordre pour toute la table.

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

## Les slugs et leur portée

Avec `slug` activé, `SlugListener` donne à chaque enregistrement `Sluggable` un *slug* tiré de `slugSource()`, fabriqué
par le slugger que nomme `sluggerName()` parmi ceux de `phil/slug-bundle`, ou par celui par défaut. Un *slug* est unique
dans sa table avec `SluggableEntity`. Avec `ScopedSluggableEntity`, il est unique dans les champs que renvoie
`slugScope()` (un locataire, une locale, un parent), et l'entité déclare l'index unique sur ces colonnes et le *slug* :

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

Deux articles intitulés « Welcome » reçoivent `welcome` en français et en anglais, et un deuxième article français
reçoit `welcome-2`.

## Les traductions

Avec `translation` activé, `TranslationListener` vérifie chaque entité de traduction au chargement de son association et
donne à sa table un index unique sur l'enregistrement et la locale. Le service public `Translations` lit la ligne d'un
enregistrement le long d'une chaîne de repli de `phil/locale-dimensions`, précharge les lignes d'une liste et donne la
ligne d'une locale à écrire. Une ligne est servie entière : la chaîne choisit une ligne, jamais un champ, et rien n'est
copié d'une autre locale. Il n'y a aucun texte source à compiler : chaque locale est une ligne à part, écrite par la
personne qui la traduit.

Un enregistrement traduit garde ce que toutes les locales partagent ; son texte vit dans une entité de traduction, avec
seulement les colonnes traduites :

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

La table `faq_translation` reçoit l'index unique `faq_translation_translatable_locale` sur `faq_id` et `locale`. La
chaîne de repli vient d'ordinaire des locales déclarées de l'application, par `chainFor()` d'un `DeclaredLocales`.

## Les instants

Les horodatages, la suppression en douceur, la désactivation, la publication et les versions figées prennent leur heure
du service `clock` de Symfony, converti en UTC : les colonnes contiennent l'instant UTC, quel que soit le fuseau
horaire de PHP ou de l'horloge. Le bundle n'écrit aucun instant de lui-même et n'associe que `RecordVersion` de la
bibliothèque : chaque colonne a le type que son trait déclare dans `phil/doctrine-behaviours`. Une horloge d'essai,
`MockClock` ou `ClockSensitiveTrait`, fixe les instants qu'un test relit.

## Les versions et le droit de revenir en arrière

`Versions::restore()` demande à Symfony Security `versions.restore_right` avec l'enregistrement comme sujet : un
*voter* peut ainsi décider enregistrement par enregistrement. Un refus est une `AccessDeniedException`, le 403 habituel.
Sans Security, ou sans *voter* qui l'accorde, personne ne revient en arrière. Une commande remplace la garde :

```yaml
services:
    Phil\DoctrineBehaviours\Contract\VersionRestoreGuard: '@Phil\DoctrineBehaviours\AllowVersionRestore'
    Phil\DoctrineBehaviours\AllowVersionRestore: ~
```

La table `phil_record_version` est créée par une migration de l'application, comme celle de toute entité associée.

## Les workers de longue durée

Rien de ce que contient un écouteur ou un résolveur ne dure au-delà d'un appel : seul `TreeListener` garde quelque chose
pendant un *flush*, et il est réinitialisé avec `kernel.reset`. Le bundle n'a besoin d'aucune autre précaution sous un
environnement d'exécution en mode *worker*.
