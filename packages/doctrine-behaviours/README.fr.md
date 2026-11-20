<!-- phil-dev source-sha256=a4b9ca9b9b4270a218928a4027190d5eca0228961e960bf7076f9a5b43782319 -->
# phil/doctrine-behaviours

**Douze comportements Doctrine, de l'horodatage et des arbres aux positions, aux slugs, aux traductions et aux versions,
chacun à activer au besoin.**

phil/doctrine-behaviours donne douze comportements aux entités de Doctrine ORM. L'horodatage note quand une entité est
créée et modifiée, ces instants étant gardés en UTC. L'auteur garde qui l'a créée et qui l'a changée en dernier, comme
identifiant d'utilisateur. La suppression douce cache une entité supprimée derrière un filtre au lieu de l'effacer, en
gardant toute sa ligne, et la supprimer une seconde fois, ou la purger, l'efface. Les arbres gardent le chemin
matérialisé et la profondeur de chaque nœud, et le lecteur `Tree` donne les racines, les enfants, les descendants, les
ancêtres et la hiérarchie. La désactivation éteint un enregistrement et le rallume derrière un filtre qui lui est
propre. Un propriétaire est un identifiant stable, jamais une adresse courriel. La publication est un état de
`symfony/workflow` avec ses dates. Une position ordonne les enregistrements dans un groupe, et un enregistrement se
déplace avant ou après un autre. Un slug fait par `phil/slug` reste unique dans sa table, ou dans une portée comme un
locataire, une locale ou un parent. Les traductions gardent le texte d'un enregistrement dans une ligne par locale, lue
le long d'une chaîne de repli déclarée et jamais recopiée d'une locale à l'autre. Les versions figent ce que tenait un
enregistrement, sans ses champs personnels sauf demande, créent la version suivante à la modification d'après, et
reviennent en arrière sous un droit qui leur est propre ; un effacement les caviarde. Un identifiant daté est un
UUID v7.

Elle s'adresse à toute application sur Doctrine ORM qui a besoin de ces colonnes sans écrire ses propres écouteurs.
Chaque comportement est indépendant : une application n'enregistre que les écouteurs qu'utilisent ses entités. Les
arbres sont des chemins matérialisés : déplacer une branche est une seule mise à jour au lieu d'une renumérotation de
l'arbre. Dans une application Symfony, `phil/doctrine-behaviours-bundle` enregistre les écouteurs, le filtre et l'auteur
qu'est l'utilisateur authentifié. Un enregistrement qui doit quitter sa table, prêt à être ramené, va plutôt dans
`phil/trash-bundle`.

C'est une librairie PHP simple sur Doctrine ORM, DBAL, `psr/clock` et `phil/locale-dimensions` ; `phil/slug`,
`symfony/uid` et `symfony/workflow` ne servent qu'aux comportements qui les nomment. Chaque comportement est une
interface, un trait qui mappe ses colonnes, et un écouteur qui les tient. Qui agit vient d'un `BlameResolver` que
fournit l'application, comme `FixedBlame` pour une commande qui agit pour un compte connu, ou pour un test.

## État

| Partie | État |
|---|---|
| `Contract\Timestampable`, `TimestampableEntity`, `TimestampListener` | Implémenté et testé |
| `Contract\Blameable`, `BlameableEntity`, `BlameListener`, `Contract\BlameResolver`, `FixedBlame` | Implémenté et testé |
| `BlameEraser` : le nom d'un utilisateur trouvé et remplacé dans chaque table qui garde l'auteur, de chaque gestionnaire d'entités | Implémenté et testé |
| `BlameSuspension` : aucun nom n'est gardé pendant qu'un effacement s'exécute | Implémenté et testé |
| `Contract\SoftDeletable`, `SoftDeletableEntity`, `SoftDeleteListener`, `SoftDeletedFilter`, `SoftDeletes` | Implémenté et testé |
| `Contract\TreeNode`, `TreeNodeEntity`, `TreeListener`, `Tree`, `Branch` | Implémenté et testé |
| `Deactivatable`, `DeactivatableEntity`, `DeactivationListener`, `DeactivatedFilter` | Implémenté et testé |
| `OwnerId`, `Ownable`, `OwnableEntity`, `OwnerListener`, `OwnerResolver`, `FixedOwner`, `OwnerEraser` | Implémenté et testé |
| `Publishable`, `PublishableEntity`, `PublicationWorkflow`, `PublicationListener` | Implémenté et testé |
| `Contract\Positionable`, `PositionableEntity`, `PositionListener`, `Positions`, groupes | Implémenté et testé |
| `Contract\Sluggable`, `SluggableEntity`, `ScopedSluggableEntity`, `SlugListener`, portées | Implémenté et testé |
| `Contract\Translatable`, `Contract\Translation`, `TranslationEntity`, `TranslationListener`, `Translations` | Implémenté et testé |
| `Versionable`, `VersionableEntity`, `VersionListener`, `Versions`, `RecordVersion`, `VersionRestoreGuard` | Implémenté et testé |
| `PersonalFields`, `PersonalFieldPolicy`, `VersionCipher`, `VersionEraser` : les champs personnels laissés de côté, scellés ou caviardés | Implémenté et testé |
| `BinaryKeysListener` : les clés comparées octet par octet sur MySQL et MariaDB | Implémenté et testé |
| `DatedIdentifiable`, `DatedIdentifierEntity`, `DatedIdentifierListener` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Horodatage et auteur](doc/fr/timestamps-and-blame.md) | Qui et quand, ce qu'une entité a reçu et garde, la vie privée et l'effacement. |
| [Suppression douce](doc/fr/soft-delete.md) | La suppression comme date, la ligne gardée en entier, le filtre, la restauration, la purge, l'ordre des écouteurs. |
| [Arbres](doc/fr/trees.md) | Les chemins, les déplacements d'une branche, la lecture d'un arbre. |
| [Désactivation](doc/fr/deactivation.md) | Éteindre et rallumer un enregistrement, le filtre, ce qui la distingue de la suppression douce. |
| [Propriétaire](doc/fr/owner.md) | Un identifiant stable, le résolveur, la remise à quelqu'un d'autre, l'effacement. |
| [Publication](doc/fr/publication.md) | La machine à états, ses dates, la publication à une heure fixée. |
| [Position](doc/fr/position.md) | L'ordre dans un groupe, les déplacements avant et après, l'index. |
| [Slug](doc/fr/slug.md) | Quand un slug est fait, comment il reste unique, dans la table ou dans une portée. |
| [Traduction](doc/fr/translation.md) | Une ligne par locale, lue le long d'une chaîne de repli, une liste en une requête, l'écriture sans copie, les slugs et les versions par locale. |
| [Versions](doc/fr/versions.md) | Figer, créer la version suivante, revenir en arrière sous un droit qui lui est propre, les champs personnels laissés de côté, scellés ou caviardés. |
| [Identifiant daté](doc/fr/dated-identifier.md) | Un UUID v7 comme clé primaire. |
| [Intégration](doc/fr/integration.md) | Enregistrer les écouteurs et le filtre sans Symfony, la collation des clés. |
| [Écritures en bloc](doc/fr/bulk-writes.md) | Ce qu'aucun écouteur ne voit, quand `postFlush` s'exécute, l'auteur et le slug qui copient des renseignements personnels. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la librairie est testée. |
| [Mises à niveau](doc/fr/upgrading.md) | Ce qui change d'une version à l'autre, et la migration des tables existantes. |

## Démarrage rapide

```php
#[ORM\Entity]
class Category implements Timestampable, Blameable, SoftDeletable, TreeNode
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeletableEntity;
    use TreeNodeEntity;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $parent = null;

    public function treeId(): string { return (string) $this->id; }
    public function treeParent(): ?self { return $this->parent; }
}
```

```php
$tree->hierarchy(Category::class);    // list<Branch>, each node with its children
$tree->ancestors($category);          // the root down to the parent
```

Dans une application Symfony, `phil/doctrine-behaviours-bundle` enregistre les écouteurs et le filtre.

## Prérequis

PHP 8.5 ou une version plus récente, Doctrine ORM 3.7 ou plus récent, DBAL 4.5, `doctrine/collections` 3.1 ou plus récent,
`psr/clock`, `symfony/polyfill-php86` et `phil/locale-dimensions` ; `phil/slug` pour les slugs, `symfony/uid` pour les identifiants datés, et `symfony/workflow`
pour la publication.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
