<!-- phil-dev source-sha256=c2af546d531c2edb9ee47033a76d8de29bf683fadf2be052dfb21d56fb2178c1 -->
# Exceptions

Chaque exception implémente `Phil\PagedMedia\Exception\PagedMediaException`.

## Le moteur d'écriture natif

Chacune étend `InvalidArgumentException`.

| Exception | Levée quand |
|---|---|
| `InvalidDocument` | Dans un document PDF/A, une police standard, un caractère absent de la police, ou une image CMYK ; un dessin avant toute page ; une taille de police qui n'est pas supérieure à zéro ; un côté de page hors de 1 à 14 400 points ; une composante de couleur hors de 0 à 255, ou une couleur qui n'est pas écrite `#rrggbb` ou `#rgb` |
| `InvalidFont` | Un fichier de police illisible, qui n'est pas du TrueType, qui a des contours PostScript, à qui manque une table obligatoire ou une correspondance Unicode, qui est tronqué, qui ne déclare aucune unité par cadratin, ou dont la licence interdit l'intégration |
| `InvalidImage` | Un fichier d'image illisible, qui n'est ni JPEG ni PNG, qui est corrompu ou tronqué, qui est un PNG entrelacé, qui a un type de couleur ou un filtre de ligne inconnu, ou un PNG indexé sans palette valide |

Chaque message nomme ce qui ne va pas, par exemple `The licence of the font "Example-Regular" forbids embedding it in a
document.`

## Le client Gotenberg

Chacune implémente `Gotenberg\Exception\GotenbergException`, qui étend `PagedMediaException`.

Une requête refusée avant d'être envoyée étend `InvalidArgumentException` :

| Exception | Levée quand |
|---|---|
| `InvalidStage` | Une option d'étape que Gotenberg refuserait : une sélection de pages, un angle, une couleur, une taille ou une opacité hors plage, une option manquante, deux options qui s'excluent, un fichier du mauvais type, une paire de profils qui ne vont pas ensemble |
| `InvalidPipeline` | Une liste d'étapes dans un ordre que Gotenberg refuse ([Étapes](stages.md)) |
| `InvalidRequest` | Aucun fichier, deux fichiers différents du même nom, un fichier illisible ou impossible à écrire, une URL qui n'est pas en HTTP absolu, une valeur d'en-tête avec un saut de ligne, des options de conversion qui s'excluent |

Une réponse qui n'est pas un succès étend la classe abstraite `GotenbergFailure`, une `RuntimeException` qui garde le
`$status` HTTP (`0` quand Gotenberg n'a pas pu être joint) et la `$trace` de la requête, et qui indique par
`isRetryable()` si la même requête peut réussir plus tard :

| Exception | Statut | Nouvelle tentative possible |
|---|---|---|
| `RequestRefused` | 400, 405, 413, 415 et les autres 4xx | non |
| `AccessDenied` | 401, 403 | non |
| `RouteUnavailable` | 404 | non |
| `ServiceUnavailable` | 429, 503, ou aucune réponse | oui |
| `ConversionFailed` | 409, 500 et les autres 5xx | non |
| `UnexpectedResponse` | 200 avec un corps d'une autre forme | non |

`CertificateUnavailable`, une `RuntimeException`, est levée par une source de certificat qui ne peut pas donner le
certificat : un coffre hors d'atteinte ou un secret d'une autre forme.

Le message d'un échec est celui de Gotenberg, après le statut : `Gotenberg answered 400: Stage 'select' failed on
'report.pdf': invalid page range: page 9 out of range [1, 3]`.
