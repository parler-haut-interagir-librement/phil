<!-- phil-dev source-sha256=55ec6e87e2c14de33c73941f01641dcaf5af17aaa4bab80b34102f8959abfc5d -->
# Règles

Les règles par défaut suivent les audits SEO de Lighthouse, dans l'ordre du rapport : la réponse, l'en-tête de la page,
son corps, ses liens. `DefaultRules::create()` les donne ; les règles de `Bridge\` s'ajoutent quand leurs bibliothèques
sont installées.

Chaque constat a une gravité :

| Gravité | Signification |
|---|---|
| `error` | Les moteurs de recherche lisent mal la page, ou ne peuvent pas l'indexer comme elle a été publiée : corrigez-la bientôt. |
| `warning` | La page est indexée, mais affichée ou atteinte moins bien qu'elle pourrait l'être. |
| `info` | Bon à savoir, peut-être voulu : rien n'a besoin de changer. |

Aucune ne bloque une publication. La vérification laisse de côté, volontairement, ce qui n'aide pas un moteur de
recherche à lire une page : la densité de mots-clés, les scores de lisibilité et une note globale unique.

Un constat cite la page par extraits : une adresse par son chemin et sa requête, une valeur coupée à 80 caractères.

## `http-status`

Chaque rendu répond par un succès : les moteurs de recherche abandonnent une page qui répond par une erreur.

| Constat | Gravité |
|---|---|
| Un rendu répond avec un statut de 400 ou plus | error |
| La page à sa propre adresse répond par une redirection : son adresse canonique est celle où les lecteurs arrivent | warning |

Un autre chemin vers la page peut rediriger. Les règles qui lisent le HTML d'un rendu — `canonical`, `indexing` —
laissent à cette règle un rendu qui redirige ou échoue.

## `page-size`

La page n'est pas plus grande que ce que la vérification lit, 5 000 000 d'octets par défaut : ce qui dépasse n'a pas été
vérifié, et les moteurs de recherche cessent eux aussi de lire une page bien avant sa fin. Un avertissement.

## `document-title`

La page a un seul `<title>`, non vide, d'une longueur qu'une page de résultats affiche en entier : c'est le lien sur
lequel les lecteurs cliquent.

| Constat | Gravité |
|---|---|
| Aucun `<title>`, ou un `<title>` vide : les pages de résultats affichent l'adresse à la place | error |
| Plusieurs `<title>` : les moteurs de recherche ne lisent que le premier | warning |
| Moins de 10 caractères, ou plus de 60, comptés en caractères et non en octets | warning |

Les bornes sont des arguments de `DocumentTitle`.

## `meta-description`

La page a une seule `<meta name="description">`, non vide : les pages de résultats l'affichent sous le titre, et
écrivent un résumé de leur cru, à partir du texte qu'elles trouvent, quand elle manque.

| Constat | Gravité |
|---|---|
| Aucune méta-description, une méta-description vide, ou plusieurs | warning |
| Moins de 50 caractères, ou plus de 160 | info |

La longueur n'est qu'une information : une page de résultats coupe une longue description, et une courte en dit peu. Les
bornes sont des arguments de `MetaDescription`.

## `document-language`

Le `lang` de l'élément `<html>` est la langue dans laquelle la page a été publiée : les moteurs de recherche montrent
une page aux lecteurs de sa langue, et les lecteurs d'écran la lisent avec la voix de cette langue. Les étiquettes de
langue se comparent comme le fait BCP 47, sans égard à la casse, `fr_CA` étant lu comme `fr-CA`.

| Constat | Gravité |
|---|---|
| Aucun `lang`, ou un `lang` vide | error |
| Une autre langue | error |
| La même langue, une autre région ou un autre système d'écriture : `fr` pour une page publiée en `fr-CA` | warning |
| Un en-tête `Content-Language` qui ne nomme ni la langue de la page ni sa langue principale | warning |

## `single-h1`

La page a un seul titre de premier niveau, avec du texte : il dit de quoi parle la page, aux lecteurs et aux moteurs de
recherche. Aucun, plusieurs, ou un sans texte, est un avertissement.

## `canonical`

La page nomme une seule adresse canonique, absolue, et c'est l'adresse où la page a été publiée : les moteurs de
recherche rassemblent ce qu'ils savent d'une page sous son adresse canonique, et indexent l'adresse qu'elle nomme plutôt
que celle qu'ils ont lue.

| Constat | Gravité |
|---|---|
| Aucun lien canonique | error |
| Un lien canonique relatif : il est résolu, puis comparé, mais devrait être écrit en absolu | warning |
| Un lien canonique qui n'est pas une adresse que les moteurs de recherche peuvent suivre | error |
| Plusieurs liens canoniques nommant des adresses différentes : les moteurs de recherche les ignorent tous | error |
| Une adresse canonique autre que celle où la page a été publiée | error |

Chaque autre rendu — un autre chemin vers la même page, `/` et `/fr/` pour une page d'accueil, ou un canal — est tenu à
la même adresse canonique. Un chemin qui en nomme une autre, ou aucune, scinde la page en deux pour les moteurs de
recherche : le constat nomme le chemin.

## `indexing`

Les directives d'indexation de chaque rendu disent ce sous quoi la page a été publiée. Les directives sont lues dans
`<meta name="robots">`, dans la `<meta>` nommée d'après un robot d'exploration — `googlebot`, `bingbot` et les autres de
`RobotsDirectives::AGENTS` —, et dans les en-têtes `X-Robots-Tag`, dont la valeur peut commencer par le robot qu'elle
vise. `noindex` et `none` gardent une page hors de l'index.

| Constat | Gravité |
|---|---|
| Une page publiée comme non indexable qu'aucune directive ne garde hors de l'index de chaque robot | error |
| Une page publiée comme indexable qu'un `noindex` ou un `none` pour chaque robot garde dehors | error |
| Une page publiée comme indexable qu'un `noindex` pour un seul robot garde hors de l'index de ce robot | warning |

Quand l'application sert la page dans plusieurs vitrines ou canaux, chaque rendu est tenu à ce que l'application dit de
son canal, par `ChannelIndexing` : un produit non vendu dans une vitrine, un canal tenu à l'écart des moteurs de
recherche. Un canal que l'application ne nomme pas suit la page.

## `hreflang`

Les traductions qu'une page liste avec `<link rel="alternate" hreflang="…">` sont valides et réciproques : les moteurs
de recherche ignorent une paire de traductions qui ne se listent pas l'une l'autre.

| Constat | Gravité |
|---|---|
| Un code qui n'est ni une étiquette de langue BCP 47, ni `x-default` | error |
| Une adresse relative, ou que les moteurs de recherche ne peuvent pas suivre | error |
| Une langue menant à plusieurs adresses | error |
| La page ne se liste pas elle-même, ou se liste sous une autre langue que la sienne | warning |
| Une traduction qui ne liste pas la page en retour | warning |

`x-default` est facultatif. Une page qui ne liste aucune traduction réussit. La réciprocité est lue par
`RenderedPages`, 50 traductions au plus par défaut ; une traduction que l'application ne produit pas n'est pas
signalée, et sans `RenderedPages` la réciprocité n'est pas vérifiée.

## `crawlable-links`

Les moteurs de recherche suivent les liens d'une page par leur `href`. Un lien avec du texte mais sans `href`, ou un
lien qui exécute un script, `javascript:…` — quelle que soit la casse, ou coupé par des tabulations et des sauts de
ligne qu'un navigateur ignore —, ne les mène nulle part : un avertissement. Un `<a>` sans `href` ni texte est une ancre,
pas un lien.

## `link-text`

Le texte d'un lien dit où il mène : les moteurs de recherche le lisent comme une description de la page vers laquelle
il pointe, et un lecteur qui parcourt une page ne lit que ses liens. Un texte comme « click here », « en savoir plus »
ou « leer más » est un avertissement.

Les textes sont comparés en minuscules, leurs espaces réduites, la ponctuation qui les entoure retirée. Les listes sont
celles de `GenericLinkTexts::DEFAULTS`, pour l'anglais, le français, l'espagnol, l'allemand, l'italien et le
portugais, choisies selon la langue principale de la page ; une application donne à `LinkText` ses propres listes. Une
page dans une langue sans liste réussit.

## `image-alt`

Chaque image a un attribut `alt` : les moteurs de recherche le lisent comme le texte de l'image, tout comme les lecteurs
d'écran. Une image sans `alt` est un avertissement. Un `alt=""` vide convient à une image qui n'est que de la décoration,
tout comme une image cachée aux lecteurs par `aria-hidden="true"` ou par un rôle `presentation` ou `none`.

## `broken-links`

Les liens de la page mènent quelque part : un lien qui répond par une erreur gaspille la visite d'un moteur de
recherche et la patience d'un lecteur. Les statuts viennent des `LinkStatuses` de l'application, tels que le dernier
vérificateur de liens les a vus ; la vérification ne demande rien, et la règle ne s'exécute que lorsque `LinkStatuses`
est donné.

| Constat | Gravité |
|---|---|
| Un lien qui répond avec un statut de 400 à 499 | error |
| Un lien qui répond avec un statut de 500 ou plus : son serveur peut revenir | warning |
| Un lien qui redirige : faites un lien vers l'endroit où il mène | info |

Chaque adresse est signalée une fois, son fragment retiré ; 1 000 adresses au plus sont interrogées par défaut. Un lien
que l'application ne connaît pas n'est pas signalé. Avec phil/link-checker, `Bridge\LinkChecker\LinkCheckerStatuses`
donne les statuts que son vérificateur a trouvés : voir [Points d'extension](extension-points.md#linkstatuses).

## `structured-data`

Avec phil/structured-data, `Bridge\StructuredData\StructuredDataRule` valide le JSON-LD de la page avec son
`Validator` : une erreur du validateur est une erreur, un avertissement un avertissement, un élément qui ne peut pas
être lu une erreur. Une page sans données structurées est une information : les résultats enrichis en ont besoin. Le
message de chaque problème est celui du validateur, en anglais, donné par le paramètre substituable `{message}`, avec
son code comme `{code}` et le nœud concerné comme `{node}`.

## `share-tags`

Avec phil/social-cards, `Bridge\SocialCards\ShareTagsRule` vérifie les balises de partage de la page avec son
`TagCheck` : les balises qu'Open Graph exige, des adresses absolues, une description, une image avec son texte et sa
taille, une carte de X, et `og:url` concordant avec le lien canonique. Une erreur de la vérification est une erreur, un
avertissement un avertissement ; le message est celui de la vérification, en anglais, donné par le paramètre
substituable `{message}`, et la balise comme `{tag}`.

## Quand une règle échoue

Une règle qui lève une exception — un port qui ne peut pas répondre — donne l'avertissement `check.rule-failed`, qui
nomme la règle et la classe de l'exception, jamais son message ; les autres règles s'exécutent quand même.
