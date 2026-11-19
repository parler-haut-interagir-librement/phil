<!-- phil-dev source-sha256=defff4b547ad3000fb1e2dddfec3fc82f7f7459a783d790ff7294863e74c2089 -->
# Versions

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Versionable` | interface | Le numéro, l'indicateur de version figée, ce que garde une version |
| `VersionableEntity` | trait | Les colonnes `version_number` et `version_frozen` |
| `VersionListener` | écouteur : `onFlush` | Crée la version suivante d'un enregistrement figé quand un champ versionné change |
| `Versions` | service | Liste, fige, revient en arrière |
| `Version\RecordVersion` | entité | Une version figée, dans la table `phil_record_version`, son `frozen_at` et son `redacted_at` de type `datetime_utc_immutable` |
| `Contract\VersionRestoreGuard` | interface | Le droit de revenir à une version passée |
| `AllowVersionRestore`, `RefuseVersionRestore` | gardes | Tout le monde, ou personne |
| `Contract\PersonalFields` | interface | Quels champs d'une classe contiennent des renseignements personnels |
| `NoPersonalFields`, `ListedPersonalFields` | champs personnels | Aucun, ou ceux listés par classe |
| `PersonalFieldPolicy` | énumération | Ce qu'une version garde des champs personnels : `Exclude`, `Encrypt` ou `Keep` |
| `Contract\VersionCipher` | interface | Scelle et ouvre les champs personnels sous `Encrypt` |
| `VersionEraser` | service | Retire les champs personnels des versions d'une personne, d'un enregistrement, ou de toutes les versions |
| `Exception\UnknownVersion`, `Exception\VersionRestoreDenied`, `Exception\InvalidVersionedState`, `Exception\VersionErased` | exceptions | |

Chaque entité active les versions en implémentant `Versionable`, et dit ce que garde une version :

```php
#[ORM\Entity]
class Rule implements Versionable
{
    use VersionableEntity;

    public function versionSubjectId(): string { return (string) $this->id; }
    public function versionedState(): array { return ['title' => $this->title, 'body' => $this->body]; }
    public function applyVersionedState(array $state): void { $this->title = $state['title']; $this->body = $state['body']; }
}
```

## Le principe

- L'enregistrement est la version en cours de travail, numérotée à partir de un.
- **Une version figée n'est jamais modifiée.** `Versions::freeze()` écrit un instantané de l'enregistrement ; dès lors,
  le changement suivant d'un champ versionné crée le numéro suivant, et l'instantané reste ce qu'il était.
- Un changement de toute autre chose, un horodatage, un état de publication, ne crée rien.
- L'événement qui fige une version relève de la décision de l'application. La publication est le cas habituel ; un
  écouteur sur `workflow.<name>.entered.published` appelle `freeze()`.

| Appel | Effet |
|---|---|
| `freeze($record)` | Vide ce qui est en attente, puis écrit l'instantané, ou rend celui qui existe |
| `list($record, $limit)` | Les versions figées, la plus récente en premier, 50 par défaut |
| `get($record, $number)` | Une version figée, ou `null` |
| `isFrozen($record, $number)` | Si ce numéro a un instantané |
| `restore($record, $number)` | Revient à une version passée, sous la garde, et vide |

`versionedState()` a pour clés des champs et des associations mappés, les seuls changements qui peuvent créer une
version : une clé que l'entité ne mappe pas est refusée par `InvalidVersionedState`. Ses valeurs sont ce que tient JSON,
et une base peut rendre les clés dans un autre ordre, MySQL par exemple. Un enregistrement nomme ses versions par sa
table racine et son identifiant, qui survivent à un renommage de la classe.

## Revenir en arrière

`restore()` ne réécrit aucun historique. La version en cours de travail est d'abord figée, pour que rien ne se perde, et
l'état de la version passée devient le numéro suivant : revenir à 1 depuis 2 laisse 1 et 2 figées, et l'enregistrement
à 3.

Revenir en arrière demande un droit qui lui est propre, distinct du droit de modifier : `VersionRestoreGuard::check()`
passe d'abord et lève quand il manque. Il n'y a pas de valeur par défaut : `Versions` prend une garde,
`RefuseVersionRestore` là où personne ne peut être consulté, `AllowVersionRestore` pour une commande que lance un
opérateur.

Revenir à une version qu'un effacement a caviardée est refusé par `VersionErased`, tout comme revenir à une version plus
ancienne qu'une version caviardée du même enregistrement : l'un comme l'autre remettraient sur l'enregistrement des
renseignements personnels effacés. Les versions figées après l'effacement se rétablissent comme avant.

## Champs personnels

Un instantané dure autant que sa ligne. Les champs que `PersonalFields` nomme pour la classe d'un enregistrement suivent
la politique donnée à `Versions` :

```php
$versions = new Versions($manager, $clock, $guard, new ListedPersonalFields([
    Member::class => ['name', 'email'],
]), PersonalFieldPolicy::Exclude);
```

| Politique | Ce qu'écrit `freeze()` | Ce que remet `restore()` |
|---|---|---|
| `Exclude`, la valeur par défaut | Les autres champs seulement | Les valeurs que l'enregistrement tient pour les champs personnels |
| `Encrypt` | Chaque champ personnel sous la forme `{"@sealed": "…"}`, scellé par le `VersionCipher` | Les valeurs que le chiffrement ouvre |
| `Keep` | Chaque champ tel que `versionedState()` le donne | Les valeurs de la version |

`Encrypt` demande un chiffrement : `Versions` lève `InvalidVersionedState` quand aucun n'est donné, tout comme
`restore()` d'une version qui contient un champ scellé quand aucun chiffrement n'est donné. Le chiffrement lie chaque
valeur à son enregistrement, à son numéro de version et à son champ : le contexte passé à `seal()` et à `open()` est
`phil_record_version|<table>|<identifier>|<number>|<field>`, et une valeur déplacée ailleurs ne s'ouvre pas. Sans
`PersonalFields`, `Versions` prend `NoPersonalFields`, et chaque champ est gardé : `versionedState()` devrait alors
laisser de côté tout ce qui est personnel et ne doit pas survivre à une demande d'effacement.

## Effacement

Le `created_by` d'une version, qui nomme celui qui l'a figée, est un auteur : `BlameEraser` l'atteint. `VersionEraser`
retire les champs personnels des instantanés eux-mêmes, dans la table, et date dans `redacted_at` chaque version qu'il
caviarde :

| Appel | Versions qu'il caviarde |
|---|---|
| `find($who)` | Aucune : combien de versions de la personne contiennent encore des champs personnels |
| `redact($who)` | Celles des enregistrements que la personne possède (`owner_id`) ou dont elle est l'auteur (`created_by`, `updated_by`), et celles qu'elle a figées, pour les classes qui ont des champs personnels |
| `redactRecord($record)` | Chaque version d'un enregistrement, quels que soient ses champs : un effacement de l'enregistrement lui-même |
| `stripPersonalFields($dryRun)` | Aucune : les champs personnels gardés en clair quittent chaque version, une valeur scellée reste |
| `tables()` | Aucune : les tables qui contiennent des versions, pour un rapport d'effacement |

`redact()` trouve les versions d'une personne par le propriétaire et par l'auteur : exécutez-le avant que `OwnerEraser`
et `BlameEraser` remplacent le nom de la personne. Une version caviardée perd les clés des champs personnels des classes
de son enregistrement, scellées ou non ; le reste de son état demeure, et `restore()` la refuse. Une seconde exécution
ne trouve plus rien et ne caviarde rien.

```php
$versions = new VersionEraser($registry, $personalFields, $clock);
$versions->redact('ada');
$owners->replace(new OwnerId('ada'));
$blame->replace('ada');
```

`stripPersonalFields()` est ce qu'une mise à niveau exécute une fois sur les versions écrites avant que les champs
personnels soient laissés de côté : il ne change aucun `redacted_at`, et l'enregistrement peut encore revenir à ces
versions, avec ses propres valeurs pour les champs retirés. Il lit les versions par lots de 500.

`VersionEraser` écrit directement dans la table, comme les autres outils d'effacement : avec un `ManagerRegistry`, il
joint la table une fois par connexion, dans une transaction de cette connexion, puis évince la région du cache de
second niveau de `RecordVersion` et détache les versions qu'il a changées. Voir [Écritures en bloc](bulk-writes.md)
pour ce que les écouteurs ne voient pas.
