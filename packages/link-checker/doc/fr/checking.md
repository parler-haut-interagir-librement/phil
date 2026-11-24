<!-- phil-dev source-sha256=c1c13ec64ce7f45bf7f3c8e8d444b949078fcb4041928b4f275d6eb35adccc59 -->
<!-- phil-dev source-sha256=x -->
# Vérifier les liens

Une vérification mène un contenu de son HTML à son rapport en quatre étapes : les liens sont lus, chaque adresse reçoit
une clé, chaque clé est vérifiée une fois, et chaque lien reçoit l'état de sa clé — et de son ancre.

## Les liens lus

`LinkExtractor::extract($html, $page)` analyse le HTML avec `Dom\HTMLDocument`, l'analyseur HTML5 de PHP, comme un
navigateur : une balise de fin manquante ou superflue, un attribut sans guillemets sont lus comme un navigateur les lit,
et un lien laissé ouvert autour d'un contenu qui suit est rouvert là, comme un navigateur le rouvre. Aucune expression
régulière ne lit jamais le HTML.

| Élément | Attributs lus |
|---|---|
| `a` | `href` |
| `img` | `src`, puis chaque adresse de `srcset` |
| `link` | `href`, sauf si son `rel` ne contient que `preconnect` ou `dns-prefetch`, qui nomment une origine |
| `iframe` | `src` |
| `source` | `src`, puis chaque adresse de `srcset` |

Les liens viennent dans l'ordre du document, chacun avec l'élément et l'attribut qui le portent et les mots que voit un
lecteur : le texte d'un lien — ou, sans texte, le texte alternatif de son image, puis son titre —, le texte alternatif
ou le titre d'une image ou d'un cadre, le `rel` d'un `link`. Ces mots et l'adresse telle qu'écrite sont gardés sur une
seule ligne, les espaces et les caractères de contrôle réduits, et coupés à 200 caractères.

Chaque adresse est résolue par rapport à l'adresse de la page, ou au premier `<base href>` du document quand il se
résout en adresse Web, selon le standard URL du WHATWG (`Uri\WhatWg\Url`), puis lue par phil/url : le schéma et l'hôte
en minuscules, un hôte international en ASCII (`bücher.example` devient `xn--bcher-kva.example`), le port par défaut
retiré.

- Une adresse d'un autre schéma — `mailto:`, `tel:`, `javascript:`, `data:`, `ftp:` — est écartée, tout comme un
  `href` vide et un `#` seul, qui désignent la page elle-même.
- Une adresse qui ne peut pas être analysée est gardée comme **invalide**.
- Une adresse que phil/url refuse — une adresse IP, des identifiants de connexion, `localhost` — est gardée comme
  **refusée**, et jamais demandée.

Un contenu est lu jusqu'à `maxBytes` (4 Mio par défaut, 64 Mio au plus) et au plus `maxLinks` liens sont gardés (2 000
par défaut, 100 000 au plus). Au-delà de l'une ou l'autre, l'extraction s'arrête et le rapport dit qu'il est tronqué.

## La clé d'une adresse

`UrlNormalizer::key()` donne la clé sous laquelle les liens ne sont vérifiés qu'une fois : l'adresse lue par phil/url,
sans son fragment. `?b=2&a=1` et `?a=1&b=2` restent deux clés, sauf si le normalisateur est construit avec
`sortQuery: true` : la plupart des sites lisent les paramètres dans n'importe quel ordre, certains non, et seul
l'appelant connaît ses liens.

```php
new LinkChecker($probe, normalizer: new UrlNormalizer(sortQuery: true));
```

Une fois triés, les paramètres sont ordonnés par nom ; ceux du même nom gardent leur ordre, que certains sites lisent.

## Les requêtes

`HttpLinkProbe` interroge chaque clé par phil/safe-http :

1. Le limiteur de débit attend qu'une requête vers l'hôte puisse partir.
2. Une requête `HEAD` : les redirections sont suivies, chacune vérifiée, dans les limites de phil/safe-http (3 par
   défaut) ; un hôte qui est un réseau privé, de bouclage, de lien local ou réservé, ou qui s'y résout, est refusé, à la
   première adresse et à chaque redirection.
3. Quand le serveur répond 405 ou 501 à `HEAD`, le limiteur de débit attend de nouveau, et un `GET` suit, dont le corps
   n'est jamais lu : le transfert s'arrête dès que l'état et les en-têtes sont reçus.

Le `User-Agent` est `PhilLinkChecker/0.1`, à moins que les `Limits` données à la sonde en fixent un autre ; un site qui
vérifie ses liens s'y nomme et y indique comment le joindre.

Seule l'adresse que nomme un lien est demandée. La sonde ne lit jamais la page d'un autre site pour y trouver d'autres
liens, et ne lit un corps que pour une page du site lui-même, afin d'y chercher une ancre.

## Les états

| État | Quand | Cassé |
|---|---|---|
| `Alive` | 2xx, sans redirection | non |
| `PermanentRedirect` | 2xx après des redirections toutes en 301 ou 308 ; `target` contient l'adresse finale | non |
| `TemporaryRedirect` | 2xx après des redirections dont une au moins est en 302, 303 ou 307 ; `target` contient l'adresse finale | non |
| `Dead` | 404 ou 410, après des redirections ou non ; ou un nom d'hôte qui ne se résout pas | oui |
| `MissingAnchor` | la page d'un lien interne a été trouvée, pas l'élément de son fragment | oui |
| `Invalid` | l'adresse telle qu'écrite ne peut pas être analysée | oui |
| `Review` | tout autre code — 429, 403, 401, 400, 5xx, 3xx sans emplacement —, un délai dépassé, une erreur TLS, une connexion rompue, trop de redirections | non |
| `Refused` | l'adresse, ou une redirection, n'est pas publique | non |

Un résultat à examiner n'est jamais compté comme mort : un serveur qui limite son débit, refuse les robots ou est hors
service un moment ne dit rien du lien. `LinkResult::$reason` nomme l'échec en quelques mots d'anglais — `timeout`,
`TLS error`, `host not found`, `too many redirects`, `not a public address` — et `$code` garde le code HTTP de la
dernière réponse.

## Les ancres

Un lien vers la page du contenu lui-même — `#intro`, ou son adresse complète avec un fragment — est cherché dans le
contenu lui-même. Un lien vers l'un des `internalHosts` donnés au vérificateur — les hôtes du site — est vérifié ainsi :

1. `InternalPages::html()` reçoit la demande de la page, sans son fragment. Quand elle donne le HTML, la page compte
   comme vivante, sans aucune requête.
2. Sinon, un lien sans fragment est sondé comme tout autre ; un lien avec fragment est sondé par un `GET` qui lit le
   HTML de la page, jusqu'aux octets des limites.
3. Dans la page trouvée — vivante ou redirigée —, le fragment doit nommer l'`id` d'un élément ou le `name` d'un
   élément `a`, tel qu'écrit ou décodé du pourcentage. Un fragment vide et `top` désignent toujours le haut de la page.

Une page lue en partie seulement — au-delà des octets des limites ou de l'extracteur — peut contenir l'élément plus bas :
ses ancres ne sont alors pas jugées, et le lien garde l'état de sa page. Les ancres d'un autre site ne sont jamais
vérifiées.

## Le rapport

Un `LinkReport` énumère les liens d'un contenu dans l'ordre du document : un lien écrit deux fois est énuméré deux fois,
chacun avec sa place, et vérifié une fois.

```php
$report->broken();      // Dead, MissingAnchor, Invalid
$report->toReview();    // Review
$report->redirected();  // PermanentRedirect, TemporaryRedirect
$report->refused();     // Refused
$report->isClean();     // no broken link
$report->summary();     // ['checked' => 12, 'broken' => 1, 'review' => 1, 'redirected' => 2, 'refused' => 0]
```

En texte, une ligne de sommaire, puis une ligne pour chaque lien qui n'est pas vivant :

```text
Links of article-42 (https://www.example.com/news/42): 12 checked, 1 broken, 1 to review, 2 redirected, 0 refused.
- dead 404: https://example.org/gone (a href "Old study")
- permanent-redirect 200: https://example.org/old (a href "Report") -> https://example.org/reports/2026
- temporary-redirect 200: https://example.net/promo (img src "Banner") -> https://example.net/promo/fall
- review 429: https://example.com/busy (a href "Shop")
```

En JSON, chaque lien, les vivants compris :

```json
{"content":"article-42","page":"https://www.example.com/news/42","truncated":false,
 "summary":{"checked":12,"broken":1,"review":1,"redirected":2,"refused":0},
 "links":[{"url":"https://example.org/gone","href":"https://example.org/gone","element":"a","attribute":"href",
           "text":"Old study","status":"dead","code":404,"target":null,"reason":null}]}
```
