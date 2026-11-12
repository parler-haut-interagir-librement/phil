<!-- phil-dev source-sha256=3c3735ca2a165366751a50245e2f65eb7ffabd76bb238a7c7d155c991a25c586 -->
# Référence

Tous les types se trouvent dans `Phil\LegalNotices` ; les ports dans `Contract`, l'exception dans `Exception`,
l'événement dans `Event`. Une valeur refusée lance `InvalidLegalNotice`. Son `reason` est un code court et stable, et
son message dit la même chose pour un humain, en citant la valeur coupée à ses 80 premiers caractères.

## Documents et champs

`DocumentType` a deux cas : `LegalNotice` (`legal_notice`) et `PrivacyPolicy` (`privacy_policy`). `requiredFields()`
liste les champs sans lesquels chacun ne peut pas être publié :

| Document | Champs requis |
|---|---|
| `legal_notice` | `publisher_name`, `publisher_address`, `publication_director`, `contact_email`, `host_name`, `host_address`, `host_contact` |
| `privacy_policy` | `publisher_name`, `contact_email`, `dpo_name`, `dpo_contact` |

`Field` a aussi d'autres champs : `site_name`, `publisher_form` (la forme juridique), `publisher_registration` (un
numéro d'entreprise ou de société) et `contact_phone`.

## SiteIdentity

`new SiteIdentity(array $values)` prend les valeurs par nom de champ. Une valeur est épurée de ses espaces de début et
de fin, et ses sauts de ligne deviennent `\n` ; une valeur vide ou `null` est un champ omis. Une valeur est en UTF-8, de
1 000 octets au plus, sans caractère de contrôle, et tient sur une ligne, sauf pour `publisher_address` et
`host_address` ; `contact_email` est une adresse de courriel. Un champ inconnu est refusé.

| Méthode | Retourne |
|---|---|
| `get(Field $field)` | La valeur, ou `null` |
| `values()` | Les valeurs données, par nom de champ |
| `missing(DocumentType $type)` | Les champs requis qui manquent à l'identité, dans l'ordre du document |

## LegalDocumentVersion

```php
new LegalDocumentVersion(
    DocumentType $type,
    int $number,                         // 1 to 999999
    array $bodies,                       // by locale, one at least, 256 KiB each at most
    string $format,                      // "text", "markdown", "ujo"…: a lowercase name of 32 characters at most
    DateTimeImmutable $effectiveAt,
    PublicationState $state = PublicationState::Draft,
    ?DateTimeImmutable $publishedAt = null,
    array $summaries = [],               // by locale, 1,000 bytes each at most
);
```

Un brouillon n'a pas de date de publication. Une version publiée ou retirée en a une, et entre en vigueur à ce moment ou
plus tard. Une locale est de la forme `fr` ou `fr_CA` (un `-` est lu comme `_`), 32 caractères au plus ; chaque corps et
chaque résumé est épuré de ses espaces de début et de fin et ne peut pas être vide.

| Méthode | Retourne |
|---|---|
| `body($locale, $fallback)` | Le corps dans la locale, sinon dans sa langue, sinon dans la locale de repli, sinon dans la première locale |
| `summary($locale, $fallback)` | Le résumé trouvé de la même façon, ou `null` |
| `locales()` | Les locales des corps |
| `isPublic()` | Si la version est publiée ou retirée |
| `isInForceAt($at)` | Si elle est publiée et que sa date d'entrée en vigueur est arrivée |
| `publish($at)` | Le brouillon publié à un moment qui ne suit pas sa date d'entrée en vigueur |
| `withdraw()` | La version publiée, retirée |

## PublicationState

| État | Affichée | En vigueur |
|---|---|---|
| `Draft` | jamais | jamais |
| `Published` | dans l'historique et à son adresse | à partir de sa date d'entrée en vigueur, jusqu'à ce qu'une version ultérieure entre en vigueur |
| `Withdrawn` | dans l'historique et à son adresse, marquée comme retirée | jamais |

## DocumentHistory

`new DocumentHistory(DocumentType $type, iterable $versions)` conserve les versions par numéro. Une version d'un autre
document, un numéro donné deux fois et plus de 1 000 versions sont refusés.

| Méthode | Retourne |
|---|---|
| `versions` | Toutes les versions, le plus ancien numéro en premier |
| `inForce($at)` | La version publiée entrée en vigueur en dernier à ce moment, le numéro le plus élevé à date égale |
| `upcoming($at)` | La version publiée qui entre en vigueur juste après ce moment, le numéro le plus élevé à date égale |
| `publicVersions()` | Les versions publiées et retirées, la plus récente en premier |
| `find($number)` | Une version publiée ou retirée par numéro, ou `null` |

La version en vigueur est celle qui est entrée en vigueur en dernier, quel que soit son numéro : une correction peut
entrer en vigueur avant une version annoncée pour plus tard.

## Conservation et consentement

`RetentionDuration($id, $label, RetentionKind $kind, ?$duration, ?$note, $labels)` :

| Genre | Durée | Note |
|---|---|---|
| `Fixed` | une durée ISO 8601, requise | ignorée |
| `UntilEvent` | aucune | l'événement, requis |
| `Indefinite`, `NotYetFixed` | aucune | ignorée |

L'identifiant est composé de lettres, de chiffres, de `.`, de `_` et de `-`, 64 caractères au plus. Le libellé dit ce
qui est conservé, en 1 à 300 octets. `label($locale)` donne le libellé de la locale ou de sa langue, sinon le libellé
par défaut.

`DurationWords::describe($retention, $locale)` donne la durée en toutes lettres, en français pour une locale française
et en anglais autrement : `P2Y6M` donne « 2 years and 6 months », « 2 ans et 6 mois ». Une durée jusqu'à un événement
est sa note ; les deux autres sont des formules fixes. Une durée compte quatre chiffres au plus par partie, peut mêler
les semaines aux autres parties, et ne peut pas être une durée nulle.

`ConsentCategory($key, $necessary, $labels, $descriptions, $cookies)` a une clé de lettres minuscules, de chiffres et de
`_`, un nom dans au moins une locale, et 100 noms de témoins au plus. Chaque nom est un jeton de témoin où `*` remplace
n'importe quelle fin.

## LegalNotices

| Méthode | Retourne |
|---|---|
| `history($type)` | L'historique que donne la source ; celui d'un autre document est refusé |
| `inForce($type, $at)` | La version en vigueur à un moment donné |
| `identity()` | Le `SiteIdentity` |
| `render($version, $locale)` | Le corps en HTML, ses paramètres remplis ; voir [Paramètres substituables](placeholders.md) |
| `retention()`, `consent()` | Ce que donnent les ports, ou rien sans eux |
| `publishedBetween($from, $to)` | Les versions publiées dont la date de publication tombe dans l'intervalle, bornes comprises |
| `check()` | Les `Problem` des documents publics |

`check()` lit chaque document qui a une version publiée ou retirée : d'abord les champs requis qui manquent à l'identité
(`missing_field`), puis, pour chaque version publique et chacune de ses locales, un corps que le moteur de rendu refuse
(`unreadable_body`), un paramètre sans valeur (`unknown_placeholder`) ou un paramètre écrit hors du texte, dans une
balise, un commentaire ou un script, qui n'est jamais rempli (`misplaced_placeholder`). Un `Problem` transporte le
document, le code, le message, ainsi que la version et la locale quand il concerne un corps.

`DocumentPublished($type, $version)` transporte les identifiants d'une version qui vient d'être publiée, pour qu'un
écouteur réagisse.
