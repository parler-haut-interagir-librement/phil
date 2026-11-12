<!-- phil-dev source-sha256=ba109a42f7fd3293adbfe299f102b738aeb37cc8925ee18d0fe943c41aa020df -->
# Fiches

Une fiche est une racine de contenu de `phil/content-bundle`, avec une ligne par version, par étape et par locale. Ses
champs sont les capacités de content-bundle — titre et description de l'extrait, *slug*, corps en Markdown, flux de
travail, référencement — et cinq capacités du répertoire :

| Capacité | Clé des données | Colonne |
|---|---|---|
| Contact | `contact` : le tableau de `ContactCardArray` | `contact` (`phil_contact`) |
| Adresse | `address` : le tableau de `AddressArray` | `address` (`phil_address`) |
| Position | `location` : `"45.5,-73.6"` | `location_latitude`, `location_longitude` |
| Liens | `related` : des identifiants de fiches par relation, `{"played_at": ["0190…"]}` | `related` (JSON) |
| Auteur de la version | `editedBy` : l'identifiant de l'utilisateur | `edited_by` |

## Qui peut faire quoi

| Action | Qui | Attribut de *voter* |
|---|---|---|
| Rédiger une fiche | toute personne connectée, qui en devient l'auteur | — |
| La modifier | son auteur, ses rédacteurs, les administrateurs | `phil_directory.edit` |
| Nommer ou retirer des rédacteurs | son auteur, les administrateurs | `phil_directory.editors` |
| Décider qui la trouve | son auteur, les administrateurs | `phil_directory.editors` |
| La publier | les administrateurs | `phil_directory.publish` |
| La lire | tout le monde, une fois publiée | — |

Un rédacteur est un identifiant d'utilisateur de 1 à 255 octets ; une fiche en a jusqu'à 50. Nommer l'auteur ou un
rédacteur deux fois ne change rien.

```php
use Phil\DirectoryBundle\Application\Actor;
use Phil\DirectoryBundle\Application\Directory;

$reference = $directory->create('podcast', 'fr', ['excerptTitle' => 'Le Gnome', 'slug' => 'le-gnome'], new Actor('ada'));
$directory->addEditor($reference, 'grace', new Actor('ada'));
$directory->edit($reference, 'fr', ['excerptDescription' => 'Un balado.'], new Actor('grace'));
$directory->publish($reference, 'fr', new Actor('admin', admin: true));
```

Une action refusée lève `ListingRefused`, une `DomainError` ; les refus propres à content-bundle, comme un *slug* déjà
pris, lèvent `InvalidContent`. Le contrôleur affiche les deux comme des erreurs de formulaire.

## Fiches liées

`related` contient les fiches qu'une fiche nomme, selon les relations de son genre ([configuration](configuration.md)).
Lorsqu'il est fourni, il remplace tout l'ensemble ; `null` le vide, et son absence le conserve. `Directory` le vérifie
avant d'écrire :

- chaque relation s'écrit sur le genre de la fiche — une autre est un `ListingRefused` ;
- chaque fiche existe, est du genre `to` de la relation, et n'est pas la fiche elle-même ;
- une fiche au plus pour une relation qui n'est pas `multiple` ;
- une relation que la configuration ne déclare plus est écartée.

```php
$directory->create('larp', 'fr', ['excerptTitle' => 'La Saga', 'related' => ['played_at' => [$site->resourceId]]], $admin);

foreach ($directory->related($site, 'fr') as $group) {
    $group->title();     // "Larps played here": the inverse title, on the larp site
    $group->listings;    // the published larps that name the site, by slug
}
```

`related()` donne les fiches publiées qu'une fiche nomme, puis celles qui la nomment, un `RelatedListings` par relation
et par sens, en laissant de côté ceux qui n'en ont aucune. Les relations propres à la fiche sont lues au moment de
servir sa page, le long de la chaîne de repli de la locale ; les fiches montrées sont celles qui sont publiées dans la
locale avec un *slug*, comme dans la liste d'un genre, lues par leurs identifiants en une seule requête. Celles qui la
nomment sont recherchées d'abord (`RelatedLookup`, dont l'adaptateur Doctrine est `DoctrineRelatedLookup`) :
l'identifiant et la colonne `related` des lignes actives de l'autre genre qui en ont, lus comme tableaux sans hydrater
d'entité, leur JSON étant parcouru en PHP — aucune requête portable ne cherche dans une colonne JSON. Seules ces fiches
sont ensuite lues comme fiches publiées, par leurs identifiants, et conservées lorsque leur ligne publiée nomme la
fiche : les fiches hydratées suivent les fiches liées, et non la taille du genre. `relationsFrom($genre)` donne les
relations qu'offre le formulaire d'un genre.

## La liste d'un genre

`Directory::published($genre, $locale, $offset = 0, $limit = null, $reader = Reader::SiteListings)` donne les fiches
d'un genre publiées dans une locale, par *slug*, en une seule lecture des lignes publiées de content-bundle
(`PublishedRowQuery`, filtré sur la colonne du genre et sur qui trouve la fiche pour le `Reader` donné), au besoin une
page de celles-ci. Une fiche publiée sans *slug* n'a pas de page vers laquelle faire un
lien : elle est laissée de côté dans la liste, dans les fiches liées et dans le plan du site.

## Qui trouve une fiche

L'auteur d'une fiche décide qui la trouve, avec l'`Indexing` de phil/indexing-consent : `Listing` implémente
`HasIndexingConsent`. Le choix se fait sur la page de ses rédacteurs, et prend effet tout de suite, sans attendre une
publication : il appartient à la fiche, pas à une version.

| Choix | La liste de son genre, les fiches liées | Le plan du site | Sa page |
|---|---|---|---|
| `Listed`, par défaut | montrée | listée | indexée |
| `NotIndexed` | montrée | laissée de côté | `noindex, noarchive` |
| `Unlisted` | laissée de côté | laissée de côté | `noindex, noarchive` |

La page d'une fiche qui n'est pas listée dit `noindex, noarchive` aux robots, dans son en-tête `X-Robots-Tag` et sa
`<meta name="robots">` ; elle reste joignable à son adresse, pour que les robots lisent la directive. La page porte le
choix sur sa requête sous `IndexingPolicy::REQUEST_ATTRIBUTE`, et phil/structured-data-bundle n'imprime alors aucun
JSON-LD pour elle.

```php
use Phil\IndexingConsent\Indexing;

$directory->changeIndexing($reference, Indexing::NotIndexed, new Actor('ada'));
$directory->listing($reference)->indexing();       // Indexing::NotIndexed
$directory->listing($reference)->isSiteListed();   // true
```

Seuls l'auteur et les administrateurs le changent ; toute autre personne est refusée par un `ListingRefused`. Le choix
est gardé dans deux colonnes de la fiche, sa valeur et si le site la liste, sur lesquelles filtrent les requêtes des
lignes publiées : la liste d'un genre et les fiches liées gardent ce que permet `Reader::SiteListings`, le plan du site
ce que permet `Reader::Sitemap`, dans la même requête unique.

## Publication et historique

Chaque modification est une version, exactement comme pour tout contenu : modifier une version publiée commence un
nouveau brouillon, et la version publiée reste en ligne jusqu'à ce qu'un administrateur publie la suivante. Chaque
version enregistre qui l'a modifiée, dans `editedBy`.

Par défaut, la modification d'un auteur ou d'un rédacteur reste un brouillon ; celle d'un administrateur est publiée
sur-le-champ. Avec `publish_every_change`, chaque modification est publiée sur-le-champ.

`Directory::history()` énumère les versions d'une fiche dans une locale, de la plus récente à la plus ancienne, chacune
avec son numéro, son titre, la personne qui l'a modifiée et le moment de sa publication, lues en une seule requête.
`Directory::find()` lit la fiche publiée d'un *slug* ; un ancien *slug* donne la page avec `movedTo`, à laquelle le
contrôleur répond par une 301.

## Les identifiants d'utilisateur et leur effacement

Une fiche conserve l'identifiant de son auteur et de ses rédacteurs, et chaque version l'identifiant de la personne qui
l'a écrite (`editedBy`) : la chaîne que porte l'acteur, que `Actors` tire du `getUserIdentifier()` du jeton de sécurité.
La page des rédacteurs montre l'auteur à ceux qui gèrent les rédacteurs, et l'historique montre l'auteur de chaque
version à l'auteur, aux rédacteurs et aux administrateurs de la fiche. Lorsque cet identifiant est une adresse de
courriel, ces personnes voient les adresses les unes des autres : une application qui ne le veut pas donne à ses
utilisateurs un identifiant opaque, ou remplace les gabarits `editors` et `history` pour afficher plutôt un nom
d'affichage.

Les identifiants sont conservés aussi longtemps que la fiche et ses versions, c'est-à-dire à vie : l'historique est
celui de la fiche. Lorsqu'un compte est effacé, l'application efface aussi son identifiant du répertoire, comme
l'exigent la Loi 25 et le RGPD :

```php
$directory->eraseUser('ada@example.com');   // how many listings and versions changed
```

`eraseUser()` retire l'identifiant des fiches qu'il a rédigées — elles n'ont plus d'auteur, et les administrateurs
nomment leurs rédacteurs — et de celles qu'il modifie, et vide le `editedBy` de chaque version qu'il a écrite, brouillon
ou publiée, en une seule transaction (`UserErasure`, dont l'adaptateur Doctrine est `DoctrineUserErasure`). Les fiches
et leurs versions restent, avec ce qu'elles disent.
