<!-- phil-dev source-sha256=80862ea9fbce8d2d14a76507771021aaa668bfef5ac228ae0f282cabb62caa3a -->
# Catalogues

Un catalogue liste les cours qu'un apprenant peut suivre. Un site a un catalogue public, montré à tous — visiteurs
déconnectés compris —, et des catalogues privés, chacun montré aux membres d'une unité d'une organisation et des unités
qui en dépendent : le service des ventes, sa succursale de l'est. Un catalogue place ses cours dans un ordre, ouvre chacun
à l'autoinscription ou non, le donne ou le vend, et l'étiquette ; un apprenant le liste filtré, trié et une page à la
fois, s'inscrit lui-même à un cours gratuit, et achète un cours avec un prix par phil/commerce-bundle.

## Définir un catalogue

`Catalogues` écrit et lit les catalogues :

```php
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;

$public = $catalogues->create('Our courses', 'Open to everyone.');
$sales = $catalogues->create('Sales training', '', CatalogueAudience::Unit, $salesUnitId);

$catalogues->offer($public->id, $welding->id);                                      // free, open to self-enrollment
$catalogues->offer($public->id, $safety->id, tags: ['Safety', 'Level 1']);
$catalogues->offer($public->id, $masterclass->id, price: CoursePrice::of(14_900, 'CAD'));
$catalogues->offer($sales->id, $negotiation->id, selfEnrollment: false);           // listed, enrolled by a manager
$catalogues->offer($public->id, $welcome->id, before: $welding->id);              // placed before another course

$catalogues->move($public->id, $masterclass->id, before: $welcome->id);
$catalogues->move($public->id, $welcome->id);                                        // after every other course
$catalogues->withdraw($public->id, $safety->id);

$catalogues->rename($public->id, 'All our courses', 'Open to everyone.');
$catalogues->showTo($sales->id, CatalogueAudience::Public);
$catalogues->remove($sales->id);
```

`offer()` ajoute le cours après les autres cours du catalogue, ou avant le cours que nomme `before`. Appelée de nouveau
pour un cours du catalogue, elle change la façon dont le catalogue l'offre — ouvert à l'autoinscription, son prix, ses
étiquettes — et garde sa place, sauf si `before` en nomme une autre. Les cours gardent leur ordre : `entries()` les donne
comme des `CatalogueEntry` — le cours, sa place à partir de 0, si un apprenant s'inscrit lui-même, son prix, ses
étiquettes.

| Règle | Refus |
|---|---|
| Un titre compte de 1 à 255 caractères, une description 10 000 au plus | `InvalidCourse` |
| Un catalogue public ne nomme aucune unité ; un catalogue d'unité en nomme une, un identifiant de 128 caractères au plus | `InvalidCourse` |
| Un prix est de 1 à 10¹² unités mineures d'une devise nommée par son code ISO 4217 ; un cours gratuit n'en a pas | `InvalidCourse` |
| Un cours porte 10 étiquettes au plus dans un catalogue, chacune de 1 à 50 lettres, chiffres, espaces ou `-`, gardée en minuscules et une seule fois | `InvalidCourse` |
| Un catalogue offre 1 000 cours au plus | `InvalidCourse` : `A catalogue offers 1000 courses at most.` |
| `before` nomme un cours du catalogue | `InvalidCourse` : `The course "…" is not in the catalogue.` |
| Le catalogue et le cours existent | `UnknownRecord` |

Un cours est offert par autant de catalogues qu'on veut, gratuit dans l'un et vendu dans l'autre. Retirer un catalogue
retire ses entrées ; retirer un cours retire ses entrées dans chaque catalogue ; dans les deux cas, les apprenants restent
inscrits.

## Catalogues publics et catalogues d'unité

`shownTo()` donne les catalogues montrés à un apprenant, par titre : les publics, et ceux des unités dont les catalogues
atteignent l'apprenant ; un visiteur déconnecté, `null`, voit les publics. `shown()` donne un catalogue, et lance
`UnknownRecord` pour un catalogue qui n'est pas montré à l'apprenant, comme pour un catalogue qui n'existe pas.

```php
$catalogues->shownTo($learner);                 // list<Catalogue>
$catalogues->shown($catalogueId, $learner);     // Catalogue, or UnknownRecord
```

Les unités viennent du port `LearnerUnits` : les unités dont l'apprenant est membre et les unités au-dessus d'elles, de
sorte qu'un membre de la succursale de l'est voit le catalogue du service des ventes qui la chapeaute. Avec
phil/organizations-bundle enregistré, `OrganizationsLearnerUnits` y répond : pour chaque organisation dont l'apprenant
est membre — l'identifiant de l'apprenant nommant le membre —, `UnitTree::unitIdsOf()` avec les unités au-dessus. Un
catalogue d'unité nomme l'identifiant de l'unité dans ce bundle et suit son arbre : une unité déplacée sous un autre
service atteint aussitôt le catalogue de ce service, une unité archivée n'en atteint aucun.

Que le catalogue d'une unité atteigne les unités en dessous d'elle relève de la configuration : avec
`phil_courses.catalogues.units_below: false`, `OrganizationsLearnerUnits` nomme seulement les unités dont l'apprenant
est membre, et le catalogue du service des ventes est montré aux membres de ce service, non à ceux de sa succursale de
l'est.

```yaml
phil_courses:
    catalogues:
        units_below: false      # the catalogue of a unit for its own members only
```

Une application qui ne l'a pas nomme son propre service `LearnerUnits` dans `phil_courses.catalogues.learner_units` ;
sans aucun, les apprenants ne voient que les catalogues publics. Les unités d'un apprenant sont lues une fois par
requête, 500 au plus.

## Lister un catalogue

`browse()` donne une page d'un catalogue telle qu'un `CatalogueQuery` la liste :

```php
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Catalogue\PriceFilter;

$page = $catalogues->browse($catalogueId, $learner, CatalogueQuery::of(
    text: 'weld',
    tag: 'safety',
    price: PriceFilter::Free,
    sort: CatalogueSort::Title,
    language: $request->getLocale(),
    page: 2,
));

$page->items;   // list<CatalogueItem>, 20 at most
$page->total;   // how many courses the query kept
$page->pages;   // how many pages, 1 at least
$page->tags;    // every tag of the catalogue, sorted
```

| Partie de la requête | Ce qu'elle garde |
|---|---|
| `text` | Les cours dont le titre ou la description contient les mots, quelle que soit la casse ; 100 caractères au plus |
| `tag` | Les cours qui portent cette étiquette |
| `price` | `any`, `free` — les cours sans prix — ou `paid` |
| `sort` | `placed`, l'ordre du catalogue ; `title`, par titre, les nombres dans leur ordre naturel ; `newest`, le cours le plus récent d'abord ; `price`, les cours gratuits d'abord, puis par devise et par montant. Les cours qui se comparent égaux gardent l'ordre du catalogue |
| `language` | Avec une langue, un groupe de [cours équivalents](equivalent-courses.md) est listé une fois : le cours de cette étiquette exacte, sinon le premier de sa langue principale, sinon le premier placé. Sans langue, chaque cours |
| `page` | La page, de 1 à 1 000 ; une page au-delà de la dernière se lit comme la dernière |

`CatalogueQuery::fromQuery()` lit la chaîne de requête d'une page — `q`, `tag`, `price`, `sort` et `page` — et une valeur
qui n'en est pas une se lit comme la valeur par défaut, de sorte qu'une adresse tapée à la main liste quand même le
catalogue ; `parameters()` écrit la requête de nouveau, ses valeurs par défaut omises, pour les liens entre les pages.

Chaque `CatalogueItem` contient le cours, son entrée et où en est l'apprenant : `enrollment`, son inscription à ce cours ;
`completed`, s'il l'a terminé ou a terminé un cours équivalent, comme `Completions` y répond — de sorte qu'une
recertification qui a fait recommencer l'inscription le montre non terminé ; `assigned`, si le cours lui est assigné
comme [formation obligatoire](mandatory-training.md) pas encore terminée. Un visiteur déconnecté n'en est nulle part.

Un listage lit les cours du catalogue, leurs entrées et leurs groupes de cours équivalents en une requête, puis quelques
requêtes pour chaque cours de la page.

## S'inscrire depuis un catalogue

```php
$enrollment = $catalogues->enroll($catalogueId, $courseId, $learner);
```

`enroll()` inscrit l'apprenant à un cours d'un catalogue qui lui est montré, ou rend l'inscription qu'il a :

| Cas | Réponse |
|---|---|
| Le catalogue n'est pas montré à l'apprenant | `UnknownRecord` |
| Le cours n'est pas dans le catalogue | `EnrollmentRefused` : `This course is not in the catalogue.` |
| L'apprenant est déjà inscrit | Son inscription |
| Le cours n'est pas ouvert à l'autoinscription | `EnrollmentRefused` : `This course is not open to self-enrollment.` |
| Le cours a un prix dans le catalogue | `EnrollmentRefused`, avec `purchase` à vrai : l'apprenant l'achète |
| Sinon | Une nouvelle inscription, avec `LearnerEnrolled` |

Un cours ouvert à l'autoinscription dans un catalogue y est inscrit depuis ce catalogue même quand
`phil_courses.self_enrollment` est faux : cette option ouvre la page de chaque cours, un catalogue ouvre les cours qu'il
liste. Un cours dont l'entrée n'est pas ouverte à l'autoinscription est listé à titre d'information, et l'application
inscrit les apprenants.

## Cours vendus par phil/commerce-bundle

Un cours avec un prix dans un catalogue est vendu, et phil/commerce-bundle reste facultatif : avec lui enregistré, le
bundle enregistre `CourseSellables`, le `SellableResolver` des références de type `course`, et `CoursePurchases`, un
écouteur de `OrderPurchased`.

1. L'apprenant demande le cours sur la page du catalogue. La page appelle le `CourseCheckout` de l'application, nommé dans
   `phil_courses.catalogues.checkout`, qui ajoute `$entry->sellable()` — `course:<catalogue>/<course>` — au panier de
   l'apprenant, une commande dont le client est l'identifiant de l'apprenant, et répond l'adresse du panier ; la page y
   redirige. Sans passage à la caisse, les cours avec un prix montrent leur prix et aucun formulaire.
2. La boutique demande à `CourseSellables` ce que vend la référence : le cours, décrit par son titre, à son prix dans ce
   catalogue, sous la catégorie de taxe `phil_courses.catalogues.tax_category`, `standard` par défaut. Un cours gratuit
   dans ce catalogue, ou qui n'y est plus, n'est rien de vendu.
3. Une fois la commande payée en entier, phil/commerce-bundle envoie `OrderPurchased`. `CoursePurchases` lit la commande
   et inscrit son client au cours de chaque ligne de type `course`, par `Catalogues::grantPurchase()`. Une commande sans
   client, ou dont le client n'est pas un identifiant d'apprenant, n'inscrit personne ; une ligne dont le cours n'est plus
   vendu dans son catalogue est laissée ; un cours payé deux fois garde une seule inscription.

Une application qui vend autrement appelle elle-même `grantPurchase()` une fois le paiement confirmé : il refuse un cours
qui n'est pas dans le catalogue ou qui y est gratuit.

## Les pages

`config/routes.php` contient trois routes :

| Route | Chemin | Ce qu'elle fait |
|---|---|---|
| `phil_courses_catalogues` | `GET /catalogues` | Les catalogues montrés au visiteur, ceux de ses unités marqués |
| `phil_courses_catalogue` | `GET /catalogues/{catalogue}` | Un catalogue : le formulaire de ses filtres et de son ordre, envoyé par GET, ses cours avec leur prix, leurs étiquettes et où en est l'apprenant, et les liens vers les autres pages ; 404 pour un catalogue non montré |
| `phil_courses_catalogue_enroll` | `POST /catalogues/{catalogue}/{course}/enroll` | Inscrit l'apprenant à un cours gratuit et l'envoie à son plan, ou l'envoie au passage à la caisse pour un cours avec un prix ; 403 déconnecté, 400 sans le jeton CSRF, 409 pour un cours qui ne lui est pas ouvert |

Les pages répondent à un visiteur déconnecté avec les catalogues publics ; le formulaire de chaque cours est montré à un
apprenant connecté, pas encore inscrit, pour un cours ouvert à l'autoinscription qui est gratuit ou vendu par un passage à
la caisse. Elles utilisent les recettes `Button`, `Badge`, `Input` et `NativeSelect` du kit, et écrivent un prix avec
`phil_courses_price()`, comme la locale de la requête l'écrit avec `ext-intl`, avec les décimales que `symfony/intl` donne
à la devise. Les textes sont en anglais et en français, dans le domaine `phil_courses` sous `catalogue.*`.

Les catalogues ne contiennent aucun renseignement personnel : ils n'ont pas besoin de module de vie privée. Les
inscriptions qu'ils font sont celles du [modèle de cours](course-model.md), exportées et effacées avec elles.
