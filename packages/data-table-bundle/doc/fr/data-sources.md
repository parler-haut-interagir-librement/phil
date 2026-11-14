<!-- phil-dev source-sha256=55514482b1c5b3d8add21362783a5b73454be18ba62916fd9d258dce690288e5 -->
# Sources de données

```php
interface DataSource
{
    public function fetch(TableQuery $query): ResultPage;

    /** @param list<string> $ids */
    public function find(array $ids): array;
}
```

`TableQuery` contient les critères (`TextCriterion`, `ChoiceCriterion`, `DateRangeCriterion`, `BooleanCriterion`), les
tris, la page et sa taille. Chaque champ qu'il contient vient de la déclaration du tableau. `ResultPage` contient les
lignes et le nombre de toutes les lignes correspondantes.

`find()` retourne les lignes de ces identifiants que contient la source. Un identifiant hors de la source est laissé de
côté, ce qui garde une action ou une modification dans le tableau d'où elle vient.

## Doctrine

```php
new DoctrineDataSource(
    $queryBuilder,
    ['id' => 'b.id', 'title' => 'b.title', 'author' => 'a.name'],
    identifier: 'id',
    fetchJoinCollection: false,
    toIdentifier: static fn (string $id): Uuid => Uuid::fromString($id),
);
```

`$fields` est la liste d'autorisation. Seules ses clés atteignent la requête, sous forme des expressions DQL auxquelles
elles correspondent. Un champ qui n'y figure pas lance `UnknownField`. Chaque valeur est un paramètre lié. Les jokers
d'un filtre de texte sont échappés. Le QueryBuilder donné n'est jamais modifié : chaque appel travaille sur une copie,
restreignez donc les lignes sur lui.

`DataTable::source()` construit l'adaptateur à chaque ouverture du tableau. Définissez `fetchJoinCollection` quand la
requête charge une collection par jointure. Une page est lue par l'`OffsetPaginator` de l'ORM, la fenêtre de la page étant
donnée à part de la requête, et son compte est le total du `WindowPage` ; `find()` lit ses lignes dans une seule fenêtre,
aussi large que les identifiants demandés, et ne lit rien du tout pour aucun.

## En mémoire

`new InMemoryDataSource($rows, 'id')` filtre, trie et pagine des lignes déjà chargées. Les champs sont des clés de
tableau ou des chemins de propriété.

## La pagination et ses bornes

Les tableaux paginent par décalage, avec des bornes. Trois limites s'appliquent à chaque requête :

- la taille de page est l'une de celles qu'offre le tableau, 500 au plus ;
- la page s'arrête au `maxOffset` du tableau, 10 000 lignes par défaut. Au-delà, le tableau montre sa dernière page
  atteignable et dit que d'autres lignes correspondent, pour que les gens resserrent les filtres ;
- une page au-delà de la dernière ligne redemande une fois la dernière page.

Les tris se terminent toujours sur l'identifiant : des lignes à égalité gardent ainsi leur place entre deux pages.

Un décalage coûte d'autant plus qu'il va loin, et le décompte s'exécute à chaque rendu. Un tableau sur un très grand
ensemble abaisse `maxOffset`, indexe ses champs triables ou implémente lui-même `DataSource`. Une implémentation peut
paginer par clé en interne, en traduisant `TableQuery::offset()` à partir de ses propres repères, ou retourner un
décompte estimé.
