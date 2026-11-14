<!-- phil-dev source-sha256=4bb62fe5ef3f07f78f896c0ee76856b6ccad03e4b898f85e79d6330837f7087d -->
# Export

Un tableau peut offrir son export : les lignes qu'il montre, filtrées et triées comme elles sont montrées, chacune de
leurs pages, dans ses colonnes visibles, en CSV ou en XLSX. Le bundle les écrit avec `phil/data-table-export`, qu'il
utilise lorsqu'il est installé :

```sh
composer require phil/data-table-export
```

## Offrir l'export

```php
$table
    ->caption('books.caption')
    ->column(Column::text('title', 'books.title'))
    ->exportable('BOOK_EXPORT', maxRows: 5_000);
```

`exportable()` prend un attribut de *voter* facultatif, demandé en plus de celui du tableau, et le nombre maximal de
lignes qu'un export écrit : 10 000 par défaut et 1 000 000 au plus. Sans phil/data-table-export, la déclaration est
conservée et rien n'est offert.

Le tableau montre alors un lien par format à côté de ses boutons, `Export as CSV` et `Export as XLSX`. Un lien porte le
tri, les filtres et les colonnes du tableau, et son contexte avec le sceau que portent les simples formulaires : un
navigateur ne peut donc pas changer ce que la page donne à la source de données.

## Ce qui est écrit

| Colonne | Écrite comme |
|---|---|
| Texte | Son texte |
| Nombre | Un nombre |
| Date | Une date, dans le fuseau horaire de la colonne, ou celui de PHP quand elle n'en nomme aucun |
| Énumération | Le libellé traduit de son choix |
| Booléen | Un booléen : `1` ou `0` en CSV, une cellule booléenne en XLSX |
| Lien | Son texte |
| Bloc | Non écrit : ce que dessine un bloc n'est pas une donnée |

Les en-têtes sont les libellés traduits des colonnes, et le titre d'une feuille de calcul XLSX est la légende du
tableau. Le CSV commence par la marque d'ordre des octets UTF-8, et un texte qu'un tableur exécuterait comme une
formule est gardé comme du texte ; voyez la documentation de phil/data-table-export.

## La route

```yaml
# config/routes/phil_data_table.yaml, the routes of the plain forms
phil_data_table:
    resource: '@PhilDataTableBundle/config/routes.php'
    prefix: /_tables
```

Le même fichier de routes ajoute `phil_data_table_export`, `GET /{table}/export.{format}`, quand phil/data-table-export
est installé. C'est un GET qui ne change rien, il ne porte donc aucun jeton CSRF : le sceau du contexte ouvre le
tableau, qui demande son *voter*, puis l'attribut de l'export ; le tri, les filtres et les colonnes sont vérifiés selon
la déclaration, comme ceux du tableau. Le fichier est envoyé en pièce jointe, `books.csv`, avec
`Cache-Control: no-store, private` et `X-Content-Type-Options: nosniff`.

## Mémoire et limites

Les lignes sont tirées de la source de données 500 à la fois pendant l'écriture du fichier : la mémoire garde donc une
page, quel que soit leur nombre. Avant toute écriture, la première page dit combien de lignes correspondent : au-delà
de `maxRows`, l'export est refusé par une `422` qui demande à la personne de restreindre le tableau avec ses filtres,
par le gabarit `@PhilDataTable/table/stopped.html.twig`. Un format inconnu, ou un tableau qui n'offre aucun export,
donne une `404` ; un sceau qui ne correspond pas, ou un *voter* qui refuse, une `403`.
