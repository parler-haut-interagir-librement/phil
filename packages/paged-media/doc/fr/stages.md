<!-- phil-dev source-sha256=fb11faf25735b85a317959554acb955c2fc423f5677e7213e6096bbe4330bd83 -->
# Étapes

Une étape est une opération sur un PDF. Elle s'exécute dans un `Pipeline` — sur `/forms/pdf/pipe`, ou après une
conversion LibreOffice — ou seule sur sa propre route avec `Gotenberg::run()`. Chaque étape vérifie ses options à sa
construction et lève `InvalidStage` pour ce que Gotenberg refuserait ; un `Pipeline` vérifie l'ordre et lève
`InvalidPipeline`.

Chaque étape implémente `Stage\Stage` : `operation()`, une `Operation` ; `options()`, les options telles que les nomme
Gotenberg, celles qui ne sont pas réglées étant omises ; `files()`, les fichiers que nomment les options, téléversés à
côté des PDF.

## Sélections de pages

Une option qui accepte des pages accepte un `Pages` ou son texte : une page (`3`), une plage (`2-5`), une liste des deux
(`1,3,5-7`), ou `all`, `odd`, `even` ou `last` seuls. Les pages se comptent à partir de 1, dans le PDF que reçoit
l'étape, et une plage est croissante. `Pages::page(3)`, `Pages::range(2, 5)`, `Pages::all()`, `odd()`, `even()` et
`last()` les construisent.

## Toutes les étapes

| Étape | Opération | Options |
|---|---|---|
| `Merge` | Ajoute les PDF bout à bout, dans l'ordre alphanumérique de leurs noms | — |
| `Split::every(n)`, `Split::ranges(...)` | Coupe chaque PDF toutes les n pages, ou en une partie par sélection ; les parties reviennent dans un ZIP et les étapes suivantes s'exécutent sur chacune | `every` ou `ranges` |
| `Select` | Garde les pages listées, dans cet ordre | `pages` |
| `Delete` | Retire les pages listées | `pages` |
| `Insert` | Insère les pages d'un autre PDF après une page, `0` avant la première | `source` (un `.pdf`), `at`, `sourcePages` |
| `Rotate` | Règle la rotation des pages, un multiple de 90 | `angle`, `pages` |
| `StampText` | Dessine une ligne de texte : un filigrane, un statut | `text`, `x`, `y`, `fontSize`, `font` (`StampFont`, les 14 polices standard), `color` (`#rrggbb`), `opacity` (de 0 à 1), `rotation`, `pages` |
| `StampImage` | Dessine une image PNG ou JPEG | `image`, `x`, `y`, `width`, `height`, `opacity`, `rotation`, `pages` |
| `StampPdf` | Dessine une page d'un autre PDF : un en-tête de lettre, un sceau, un filigrane | `source`, `sourcePage`, `x`, `y`, `scale`, `opacity`, `rotation`, `pages` |
| `SetMetadata` | Écrit les informations du document ; un champ non réglé garde sa valeur | `title`, `author`, `subject`, `keywords`, `creator`, `producer`, `creationDate`, `modDate` |
| `SetBookmarks` | Remplace le plan par des `Bookmark`, imbriqués par leurs `children` | `bookmarks` |
| `SetForm` | Remplit des champs par leurs noms pleinement qualifiés | `fields` |
| `FlattenForm` | Incruste les champs de formulaire dans les pages | — |
| `FlattenTransparency` | Remplace le contenu transparent par du contenu opaque | — |
| `ConvertColors` | Convertit toutes les couleurs vers un seul espace | `target` (`ColorTarget` : `cmyk`, `rgb`, `gray`, `output-intent`) |
| `Redact` | Retire du fichier lui-même le contenu sous chaque `Region` | `regions` |
| `Autotag` | Déduit un arbre de structure, pour que les lecteurs d'écran puissent suivre le PDF | — |
| `Attach` | Intègre un fichier | `file`, `name`, `mimeType`, `relationship` (`Relationship`, qui en fait un fichier associé PDF/A-3) |
| `Detach` | Retire un fichier intégré par son nom | `name` |
| `Optimize` | Réécrit le PDF en plus petit sans perte de qualité ; chaque passe s'exécute sauf si elle est désactivée | `objectStreams`, `dedup`, `recompressImages`, `subsetFonts` |
| `Encrypt` | Protège le PDF par un mot de passe d'utilisateur, un mot de passe de propriétaire, ou les deux | `userPassword`, `ownerPassword`, `algorithm` (`EncryptionAlgorithm`, AES-256 par défaut), `permissions` (`Permissions`) |
| `Decrypt` | Ouvre des PDF chiffrés et retire leur chiffrement | `password` |
| `Remediate` | Répare le PDF jusqu'à ce qu'il soit conforme à PDF/A, PDF/UA ou Factur-X ([Conformité](conformance.md)) | `profile`, `language`, `aggressive`, `figureAltText`, `xml`, `level`, `documentType` |
| `Sign` | Signe le PDF avec un certificat ([Signature](signing.md)) | `certificate`, `key`, `certificatePassword`, `reason`, `location`, `contactInfo`, `name`, `pades`, `timestampUrl` |
| `Timestamp` | Ajoute un horodatage de document RFC 3161 ([Signature](signing.md)) | `timestampUrl` |
| `Render` | Dessine des pages sous forme d'images, qui reviennent à côté du PDF | `pages`, `dpi`, `format` (`ImageFormat`), `quality` (JPEG seulement) |
| `ExtractText` | Renvoie le texte qu'affichent les pages, séparées par un saut de page | `pages`, `invisible`, `artifacts`, `annotations` |
| `ExtractAttachments` | Renvoie les fichiers intégrés, ou celui qui est nommé | `name` |

Une date donnée à `SetMetadata` sous forme de `DateTimeInterface` est écrite comme date PDF en UTC
(`D:20260101120000Z`) ; un texte est transmis tel quel : les informations qu'a lues `Gotenberg::metadata()` repartent
donc avec `SetMetadata::from()`.

## Règles d'ordre

Gotenberg vérifie l'ordre d'une liste avant d'exécuter la moindre étape ; `Pipeline` applique les mêmes règles et
refuse la liste à sa construction :

- `decrypt` vient en premier, et `render`, `extract-text` ou `extract-attachments` en dernier ;
- une liste contient au plus un `remediate`, un `encrypt`, un `sign` et un `timestamp` ; un seul `remediate` associe un
  profil PDF/A et un profil PDF/UA ;
- après `remediate`, seuls `encrypt`, `optimize`, `sign`, `timestamp` et une étape terminale peuvent suivre ; après
  `encrypt`, seuls `optimize` et une étape terminale ; après `sign` ou `timestamp`, seuls `sign`, `timestamp` et une
  étape terminale ;
- `encrypt` ne se combine pas avec `sign`, `timestamp`, ni avec un `remediate` PDF/A ou Factur-X ;
- `decrypt` ne se combine pas avec `sign` ni `timestamp` : signez le PDF déchiffré dans une requête à part.

```php
new Pipeline(new Remediate(Profile::PdfA2b), new Rotate(90));
// InvalidPipeline: "rotate" cannot follow "remediate": only encrypt, optimize, sign, timestamp, render, extract-text and extract-attachments may.
```

`Pipeline::then()` ajoute des étapes selon les mêmes règles, `contains()` indique si une opération est dans la liste,
et `toJson()` donne le champ `stages`.

## Ce qu'une étape fait au fichier

Une étape réécrit le PDF en entier ou lui ajoute une mise à jour. Une réécriture fait disparaître les signatures :
Gotenberg refuse donc ces étapes sur un PDF signé. Traitez, puis signez. `Operation::rewrites()` les distingue :

| Le fichier | Étapes |
|---|---|
| Réécrit | `select`, `delete`, `insert`, `merge`, `split`, `encrypt`, `decrypt`, `flatten-form`, `redact`, `remediate`, `convert-colors`, `flatten-transparency`, `optimize` |
| Mis à jour, signatures gardées | `rotate`, les estampilles, `set-form`, `set-metadata`, `set-bookmarks`, `attach`, `detach`, `autotag`, `sign`, `timestamp` |
| Inchangé | `render`, `extract-text`, `extract-attachments` |
