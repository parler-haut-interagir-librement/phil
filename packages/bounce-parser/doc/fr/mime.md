<!-- phil-dev source-sha256=2102ce051a536abb0227ef594909e31523de1983dab70a374c624a5301f0d0dc -->
# MIME

`Mime\MimeParser::parse(string $raw): Mime\Part` lit un message Internet (RFC 5322) et sa structure MIME (RFC 2045, RFC
2046). Il ne lève jamais d'exception : ce qu'il ne peut pas lire est gardé comme texte.

| Lu | Comment |
|---|---|
| Champs d'en-tête | Dépliés et épurés, les noms comparés sans égard à la casse ; une ligne sans deux-points, ou un nom avec une espace, est sautée |
| Mots encodés (RFC 2047) | Décodés en UTF-8 par `Headers::decoded()`, sur demande |
| Type de média | En minuscules, `text/plain` s'il manque ou est mal formé (`message/rfc822` dans un condensé), paramètres par nom en minuscules, valeurs entre guillemets déséchappées |
| Encodages de transfert | `base64` (caractères hors de son alphabet ignorés, une longueur impossible ne donne rien) et `quoted-printable` défaits |
| Multiparties | Découpées à leurs lignes de frontière, préambule et épilogue retirés ; une ligne de fermeture manquante termine la multipartie avec le texte |
| Messages contenus | `message/rfc822` et `message/global` lus comme le message qu'ils contiennent, dans `Part::$message` |

Une partie qui commence par une ligne vide n'a aucun champ d'en-tête.

## Parties

| Membre | Contient |
|---|---|
| `headers` | Les `Mime\Headers` : `get()`, `all()`, `has()`, `decoded()`, `fields` |
| `type`, `parameters` | Le type de média et ses paramètres |
| `body` | Le corps, son encodage de transfert défait, dans son propre jeu de caractères |
| `parts` | Les parties enfants d'une multipartie |
| `message` | Le message que contient un `message/rfc822` |
| `walk(bool $intoMessages = false)` | Cette partie et chaque partie sous elle, en profondeur d'abord |
| `find(string $type)` | La première partie d'un type de média, sans entrer dans les messages contenus |

## Limites

`Limits` borne le travail sur un message, quoi qu'il contienne :

| Limite | Valeur par défaut | Au-delà |
|---|---|---|
| `maxBytes` | 1 Mio | Non lu : un rapport met d'abord ce qui compte, le message original à la fin |
| `maxDepth` | 8 | Une multipartie n'est pas découpée, un message contenu n'est pas lu |
| `maxParts` | 64 | Aucune autre partie n'est lue |
| `maxHeaders` | 500 | Aucun autre champ d'une partie n'est lu |
| `maxText` | 64 Kio | Le texte qu'examine une règle ou la lecture d'un rebond en texte est coupé |

Chaque limite vaut au moins 1, sinon `Exception\InvalidLimits`.
