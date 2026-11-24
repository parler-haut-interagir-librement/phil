<!-- phil-dev source-sha256=45656026cc4507e3e8390cc9e2288cbf345b78e7e8a78c5c8fcfae0bdfc12959 -->
# Pages

## Les routes

| Route | Chemin | Ce qu'elle montre |
|---|---|---|
| `phil_newsletter_archive` | `/` | Les numéros publiés du site, du plus récemment envoyé au plus ancien, une page à la fois. |
| `phil_newsletter_archive_issue` | `/{slug}` | Un numéro publié. |
| `phil_newsletter_archive_link` | `/shared/{token}` | Un numéro partagé par un lien, publié ou non. |

La liste lit trois paramètres de requête : `list`, la clé d'une liste ; `tag`, une étiquette ; `page`, de 1 à 1000. Un
paramètre mal formé, une page au-delà de la dernière, un slug sans numéro publié, un jeton révoqué, mal formé ou jamais
émis, et un jeton du numéro d'un autre site répondent tous 404.

## Les gabarits

| Gabarit | Variables |
|---|---|
| `@PhilNewsletterArchive/archive/layout.html.twig` | La page que les autres étendent, avec les blocs `title`, `meta` et `body`. |
| `@PhilNewsletterArchive/archive/index.html.twig` | `page` (`IssuePage`), `lists` (clé ⇒ nom), `tags`, `list`, `tag`. |
| `@PhilNewsletterArchive/archive/issue.html.twig` | `issue` (`ArchivedIssue`), `shared` (s'il est lu par son lien). |

Les gabarits écrivent du HTML simple — titres, listes, `nav`, `article`, `time` — que la feuille de style de phil-ux
habille sans classe ; les rares classes qu'ils portent, `phil-newsletter-archive__…`, sont des crochets pour le style
propre à une application. Une application les remplace dans `templates/bundles/PhilNewsletterArchiveBundle/`, la mise en
page d'abord, pour porter les siens. Les textes sont dans le domaine de traduction `phil_newsletter_archive`, en anglais
et en français.

La version Web est écrite sans échappement, `issue.html|raw` : elle a été purifiée par `phil/markup` à la publication du
numéro.

## Moteurs de recherche et partage

La page d'un numéro publié donne son adresse canonique, `<link rel="canonical">`, ainsi que son type, son titre, son
adresse et sa description Open Graph — le texte d'aperçu. La liste donne l'adresse canonique de sa page et de ses filtres.

Une image de partage revient à l'application : le bloc `meta` de la mise en page est l'endroit où la mettre, avec
`phil/social-cards` par exemple.

## Une page partagée

La page d'un lien répond `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer` et
`X-Robots-Tag: noindex, nofollow`, et dit la même chose dans ses éléments `meta` : aucun cache ne la garde, aucun moteur
de recherche ne l'indexe, et aucun `Referer` ne porte son jeton vers les sites qu'elle lie. Elle ne donne d'adresse
canonique que si le numéro est aussi publié, et ne lie ses étiquettes que dans ce cas.
