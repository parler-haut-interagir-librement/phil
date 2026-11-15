<!-- phil-dev source-sha256=66e99e18ade0e0b8089dcaa4c7e9caa2e7243f41e4da315fa802bde1d18e97f8 -->
# Le modèle

Chaque format est lu dans un seul modèle et écrit à partir de lui. Chaque type est immuable : un changement retourne une
nouvelle valeur.

## Les instants

Un instant est un `int` de millisecondes depuis le début du média, de 0 à `Timecode::MAX` (9999 heures, 59 minutes,
59,999 secondes). `Timecode` lit et écrit chaque forme écrite :

| Méthode | Forme |
|---|---|
| `parseWebVtt()`, `formatWebVtt()` | `01:02:03.004`, ou `02:03.004` sans les heures en lecture |
| `parseSrt()`, `formatSrt()` | `01:02:03,004` ; un point et moins de chiffres sont aussi lus |
| `fromSeconds()`, `toSeconds()` | `3723.004` comme l'écrit JSON, un entier quand l'instant tombe sur une seconde |
| `parseClock()`, `formatClock()` | `1:02:03` ou `2:03`, l'horloge affichée à côté d'un paragraphe |
| `parseDuration()`, `formatDuration()` | `PT3723.004S`, le `datetime` d'un `<time>` HTML |

Les secondes lues dans du JSON sont arrondies à la milliseconde ; rien de plus fin n'est gardé.

## La piste et les repères

Un `Track` contient ses `Cue` dans l'ordre, et ce que seul WebVTT porte : l'`header` après `WEBVTT`, les `Region`, les
`styles` — le CSS de chaque bloc `STYLE`, comme texte — et les `Note`.

Un `Cue` a un `start`, un `end` à son début ou après, son `text`, un `id` facultatif et ses `CueSettings`. Le texte
d'un repère est du texte de repère WebVTT dans tous les formats : du balisage comme `<v Alice>`, `<i>` ou `<c.loud>`, et
`&amp;`, `&lt;` et `&gt;` pour les caractères qu'ils représentent. Un repère refuse un texte avec un retour chariot, une
ligne vide ou `-->`, qui briserait le bloc où il est écrit ; construisez le texte à partir de texte brut avec
`CueText::fromPlain()`.

```php
use Phil\TimedText\Cue;
use Phil\TimedText\CueText;

$cue = new Cue(1_000, 4_500, CueText::fromPlain('Fish & chips', speaker: 'Alice'));
$cue->text;        // '<v Alice>Fish &amp; chips'
$cue->plainText(); // 'Fish & chips'
$cue->speaker();   // 'Alice'
```

## Le texte de repère

| Méthode | Retourne |
|---|---|
| `CueText::plain()` | Le texte sans balisage, ses références de caractères décodées |
| `CueText::speaker()` | La voix qui ouvre le texte, ou nul |
| `CueText::voices()` | Chaque voix que nomme le texte, une fois chacune |
| `CueText::fromPlain()` | Du texte de repère à partir de texte brut, échappé, lignes vides retirées, ouvert par la voix de l'intervenant |
| `CueText::fromSrt()` | Du texte de repère à partir de texte SRT : `<b>`, `<i>` et `<u>` gardés, autres balises et remplacements `{\an8}` écartés |
| `CueText::toSrt()` | Du texte SRT : `<b>`, `<i>` et `<u>` gardés, autres balises écartées, références décodées |
| `CueText::toHtml()` | Du HTML, échappé par construction — voir [Sécurité](security.md) |

## Notes, régions et styles

Une `Note` est un commentaire `NOTE` ancré à un instant : elle est écrite avant le premier repère qui commence à son
`at` ou après, ou après tous les repères quand `at` est nul. Décaler une piste déplace les ancres avec les repères.

Une `Region` a un `id`, que les repères nomment dans leur réglage `region`, et ses réglages `width`, `lines`,
`regionanchor`, `viewportanchor` et `scroll`. `CueSettings` contient les réglages `vertical`, `line`, `position`,
`size`, `align` et `region` d'un repère, dans l'ordre écrit ; un réglage ou une valeur que WebVTT n'autorise pas est
refusé quand il est construit à la main et écarté, avec un problème, quand il est lu.

Les styles ne sont jamais interprétés : ce sont des textes gardés pour le prochain fichier WebVTT, ou écartés par
`StylePolicy::Drop`.

## Modifier une piste

| Méthode | Ce qu'elle fait |
|---|---|
| `Track::shifted($ms)` | Déplace chaque repère et chaque note ; déplacé plus tôt, un repère qui se terminerait avant 0 est écarté et un qui commencerait avant 0 commence à 0 |
| `Track::mergedWith($other)` | Une piste à partir de deux, repères ordonnés par début ; les régions, styles et notes de l'autre sont ajoutés |
| `Track::splitAt($ms, $rebase)` | Deux pistes, un repère à cheval sur l'instant coupé entre elles ; avec `$rebase`, la seconde commence à 0 |
| `Track::sorted()` | Les repères ordonnés par début, puis par fin, les égalités gardées dans leur ordre |
| `Track::at($ms)`, `between($from, $to)` | Les repères affichés à un instant, ou pendant `[$from, $to)` |
| `Track::withCues()`, `withoutStyles()` | La même piste avec d'autres repères, ou sans ses styles |
| `Cue::shifted()`, `splitAt()`, `mergedWith()` | La même chose sur un seul repère |

```php
// Remove the first ninety seconds of a recording and the captions that went with them.
[, $rest] = $track->splitAt(90_000, rebase: true);
```
