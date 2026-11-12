<!-- phil-dev source-sha256=dba714e0f774c94c43de1761de6a9ea108258fbc501fd2c0e211cc2f311962ae -->
# Aperçus et vérifications

## Lire les balises d'une page

`TagReader::read($html, $url, $charset)` lit une page que personne ici n'a écrite. Il coupe la page à `$maxBytes`
(512 Kio par défaut) et à la fin de son en-tête, analyse ce qui reste avec l'analyseur HTML5 de PHP, et lit :

- le `<title>`, sur une ligne ;
- le premier `<link>` dont le `rel` contient `canonical`, résolu par rapport à l'adresse de la page ;
- le `content` de chaque `<meta>` selon sa `property`, sinon son `name`, en minuscules.

Au plus 300 balises et 300 liens sont lus, au plus 10 valeurs sont gardées pour une clé, une clé plus longue que 100
caractères est abandonnée, et chaque valeur est mise sur une ligne et coupée à 2 048 caractères. Une balise que la coupe
laisse inachevée est abandonnée, comme l'abandonne l'analyseur HTML5. Le jeu de caractères qu'a déclaré le serveur est
employé quand PHP le connaît ; sinon, l'analyseur lit le `<meta charset>` propre à la page. Une page qui n'est pas du
HTML donne des balises vides ; le lecteur ne lance jamais d'exception.

## Les aperçus de lien

`LinkPreview::fromTags()` fait la carte d'un lien à partir des balises :

| Champ | Le premier de |
|---|---|
| `url` | `og:url`, le lien canonique — chacun sur l'hôte de la page lue seulement —, l'adresse de la page |
| `title` | `og:title`, `twitter:title`, `<title>`, l'hôte |
| `description` | `og:description`, `twitter:description`, `description` |
| `image` | `og:image`, `og:image:url`, `og:image:secure_url`, `twitter:image`, `twitter:image:src` |
| `imageAlt` | `og:image:alt`, `twitter:image:alt` |
| `siteName` | `og:site_name`, l'hôte |
| `type`, `locale` | `og:type`, `og:locale` |

Chaque adresse est résolue par rapport à l'adresse de la page, puis lue par phil/url, et gardée seulement quand c'est
une adresse HTTP ou HTTPS absolue, sans identifiants, sur un nom d'hôte public : `javascript:` et `data:` n'en sortent
jamais, ni une adresse IP, `localhost`, un nom sous `.localhost` ou un nom d'une seule étiquette comme `intranet`. Une
adresse sort sous la forme canonique de phil/url : hôte en minuscules et en ASCII, port par défaut retiré.

L'adresse de l'aperçu est l'adresse de la page lue, celle où le récupérateur a abouti. Une page nomme sa propre
adresse dans `og:url` et dans son lien canonique, mais rien n'empêche une page d'y nommer l'adresse d'un autre site, et
un aperçu montré sous cette adresse passerait pour ce site. `og:url`, puis le lien canonique, remplace l'adresse de la
page seulement quand il est sur le même hôte et le même port ; `www.example.com` n'est pas `example.com`. Quand
l'adresse de la page n'est pas une adresse que phil/url accepte, elle est gardée telle quelle et aucun des deux ne la
remplace.

`toArray()` et `fromArray()` gardent un aperçu dans un cache ; `fromArray()` relit ses adresses avec phil/url et écarte
un aperçu dont l'adresse n'est pas acceptée.

## Trouver les liens d'un texte

`LinkFinder::find($text, $max = 10)` trouve les liens qu'un membre a écrits ou collés dans un texte brut, dans l'ordre
où ils apparaissent, chacun une fois ; `LinkFinder::first($text)` donne le premier, le lien qu'un message prévisualise.

- Un lien commence par `http://` ou `https://`, hors d'un mot, et va jusqu'à la prochaine espace, au prochain guillemet,
  chevron, accolade, accent grave ou caractère de contrôle.
- Les signes qui terminent une phrase après lui n'en font pas partie : `.`, `,`, `;`, `:`, `!`, `?`, `'`, `»`, `”`, `’`,
  `›` et `…` ; une parenthèse ou un crochet fermant qu'il n'a pas ouvert non plus : `(see https://example.org/wiki/A_(b))`
  donne `https://example.org/wiki/A_(b)`.
- Les 64 premiers Kio du texte sont lus, en UTF-8, un octet invalide étant remplacé ; un lien de plus de 2 048 octets est
  laissé de côté ; l'analyse s'arrête à `$max` liens. Retirer des signes à la fin d'un lien coûte la même chose pour
  chaque signe, quelle que soit sa longueur.

Il ne vérifie que la forme d'un lien : qui en récupère un vérifie l'adresse entière et ce à quoi son hôte se résout.

## Vérifier une page

`TagCheck::check($tags)` retourne ce sur quoi trébucheraient les sites qui affichent des liens :

| Sévérité | Constat |
|---|---|
| Erreur | Un `og:title`, `og:type`, `og:url` ou `og:image` absent ; un `og:url` ou `og:image` qui n'est pas absolu ; un `twitter:card` qui n'est pas `summary`, `summary_large_image`, `app` ou `player` |
| Avertissement | Un `og:description` ou `twitter:card` absent ; un titre de plus de 90 caractères ou une description de plus de 200 ; une image sans son texte de remplacement, sans sa taille, ou plus petite que 200×200 ; un `og:url` autre que le lien canonique ; un `fediverse:creator` qui n'est pas un compte |

`TagCheck::hasErrors()` dit si un constat est une erreur.
