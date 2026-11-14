<!-- phil-dev source-sha256=1ad86965bf4900106ed6fd45d9ea8ab5fd30c5106b6e5faeef24de8b622d885d -->
# phil/data-table-bundle

**Des tableaux de données qui trient, filtrent, paginent, agissent et modifient sur place, avec ou sans JavaScript.**

phil/data-table-bundle dessine un tableau d'enregistrements sous forme de Live Component de Symfony UX. Un tableau se
déclare en PHP : ses colonnes, ses filtres, ses actions, son tri par défaut et ses tailles de page. Il offre le tri, des
filtres de texte, d'énumération, de plage de dates et booléens, la pagination, des actions sur une ligne ou sur une
sélection, et la modification d'une cellule sur place. Sur un écran étroit, ses lignes deviennent une liste empilée.

Il s'adresse à une interface d'administration ou à une page de catalogue qui liste des enregistrements et permet d'y
travailler. Choisissez-le quand l'état du tableau doit survivre à un lien ou à un rechargement, et quand les
modifications doivent être vérifiées sur le serveur à chaque fois. Ce n'est pas une grille côté client. Avec
`phil/data-table-export`, un tableau exporte ses lignes filtrées et triées en CSV ou en XLSX.

L'état d'un tableau est dans l'adresse : un lien, un rechargement ou un navigateur sans JavaScript montre donc le même
tableau. Chaque contrôle est un simple lien ou un simple formulaire que le composant prend en charge quand il le peut.
Les lignes viennent du port `DataSource`, avec un adaptateur en mémoire et un adaptateur pour le QueryBuilder de
Doctrine. Les champs de tri et de filtre ne viennent que de ce que déclare le tableau. Les actions et les modifications
demandent un attribut de *voter* à chaque appel et passent une vérification CSRF sans état.

Le bundle s'appuie sur Live Component et Twig Component de Symfony UX, Validator et CSRF. Ses boutons sont la recette
Button du kit PHIL UX — ou du kit Shadcn —, installée dans l'application par le Toolkit de Symfony UX. Doctrine ORM,
Symfony Security, AssetMapper, `phil/settings-bundle` et `phil/data-table-export` sont facultatifs, et chacun est
utilisé lorsqu'il est installé. La feuille de style lit les jetons de PHIL UX et a ses propres valeurs sans lui.

## État

| Partie | État |
|---|---|
| La déclaration d'un tableau : `TableBuilder`, `Column`, `Filter`, `Action`, `Editing` | Implémenté et testé |
| Les cellules en bloc, par les thèmes de cellules de l'application : `Twig\BlockChain` | Implémenté et testé |
| `DataSource`, `InMemoryDataSource`, `DoctrineDataSource` | Implémenté et testé |
| L'état vérifié selon la déclaration, les liens de chaque état | Implémenté et testé |
| Le composant `PhilDataTable:Table`, ses gabarits, sa feuille de style et son contrôleur Stimulus | Implémenté et testé |
| Les actions sur une ligne et en lot, la modification sur place, les routes des simples formulaires | Implémenté et testé |
| La visibilité des colonnes et la taille de page par utilisateur : `TablePreferences`, `SettingsTablePreferences`, un refus journalisé | Implémenté et testé |
| La question d'une action dans un `<dialog>` natif, affiché dans la page sans JavaScript | Implémenté et testé |
| Un avis quand la ligne en cours de modification quitte la page | Implémenté et testé |
| Les boutons dessinés avec la recette Button du kit | Implémenté et testé |
| L'export en CSV ou en XLSX, avec `phil/data-table-export` | Implémenté et testé |
| La suite au navigateur : filtres, colonnes, focus et dialogue dans Chrome | Implémenté et testé |
| La recette Flex : le bundle, sa configuration et ses routes | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la recette, les routes, les ressources, la configuration. |
| [Tableaux](doc/fr/tables.md) | La déclaration des colonnes, des filtres, des tris et des tailles de page ; le composant. |
| [Sources de données](doc/fr/data-sources.md) | Le port, les adaptateurs, la pagination et ses bornes. |
| [Actions et modification](doc/fr/actions-editing.md) | Les actions sur une ligne et en lot, la modification sur place, ce qui est vérifié et quand. |
| [Export](doc/fr/export.md) | L'export d'un tableau en CSV ou en XLSX, sa route, ses vérifications et ses limites. |
| [L'état dans l'adresse](doc/fr/state.md) | Les paramètres, plusieurs tableaux sur une page, le repli sans JavaScript. |
| [Accessibilité](doc/fr/accessibility.md) | Ce que dit le balisage, le clavier, la liste empilée. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/data-table-bundle
```

```php
final readonly class BooksTable implements DataTable
{
    public function __construct(private EntityManagerInterface $entities)
    {
    }

    public static function name(): string
    {
        return 'books';
    }

    public function configure(TableBuilder $table): void
    {
        $table->caption('books.caption')
            ->column(Column::text('title', 'books.title')->sortable())
            ->column(Column::date('published', 'books.published')->sortable())
            ->filter(Filter::text('q', 'books.search', ['title']))
            ->sortBy('title');
    }

    public function source(array $context): DataSource
    {
        return new DoctrineDataSource(
            $this->entities->createQueryBuilder()->select('b')->from(Book::class, 'b'),
            ['id' => 'b.id', 'title' => 'b.title', 'published' => 'b.published'],
        );
    }
}
```

```twig
<twig:PhilDataTable:Table name="books" />
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` et `ext-mbstring` ; config, injection de dépendances, HTTP foundation, HTTP
kernel, property access, routing, CSRF, translation et validator de Symfony 8.2 ; Live Component et Twig Component de
Symfony UX 3.7 ; Twig 3.29 ; la recette Button du kit PHIL UX, ou du kit Shadcn. Doctrine ORM, Symfony Security,
AssetMapper, `phil/settings-bundle` et `phil/data-table-export` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
