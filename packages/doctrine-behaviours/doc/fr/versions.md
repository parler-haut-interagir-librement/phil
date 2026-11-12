<!-- phil-dev source-sha256=9eb5d82d1c15e39880cc1971aa91ed904c29dc90d70bfe933eb66d857ff00bb1 -->
# Versions

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Versionable` | interface | Le numéro, l'indicateur de version figée, ce que garde une version |
| `VersionableEntity` | trait | Les colonnes `version_number` et `version_frozen` |
| `VersionListener` | écouteur : `onFlush` | Crée la version suivante d'un enregistrement figé quand un champ versionné change |
| `Versions` | service | Liste, fige, revient en arrière |
| `Version\RecordVersion` | entité | Une version figée, dans la table `phil_record_version` |
| `Contract\VersionRestoreGuard` | interface | Le droit de revenir à une version passée |
| `AllowVersionRestore`, `RefuseVersionRestore` | gardes | Tout le monde, ou personne |
| `Exception\UnknownVersion`, `Exception\VersionRestoreDenied`, `Exception\InvalidVersionedState` | exceptions | |

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

## Vie privée

Le `created_by` d'une version, qui nomme celui qui l'a figée, est un auteur : `BlameEraser` l'atteint. Un instantané
tient ce que donne `versionedState()` et dure autant que sa ligne : cette méthode laisse donc de côté tout renseignement
personnel qui ne devrait pas survivre à une demande d'effacement.
