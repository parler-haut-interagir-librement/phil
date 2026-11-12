<!-- phil-dev source-sha256=60cb28ba41545cdcd0c3d57d748d02e1b527d2575d44e0a19586e264b7142044 -->
# Segments

Un `Segment` est un morceau d'un document à traduire dans une langue. C'est une valeur : chaque changement retourne
une copie.

| Propriété | Sens |
|---|---|
| `key` | L'endroit où se trouve le morceau dans son document : de 1 à 255 octets d'UTF-8, unique dans le document |
| `source` | Le texte à traduire, en HTML en ligne |
| `fingerprint` | La `Fingerprint` de la source, calculée |
| `state` | Un `SegmentState` |
| `target` | La traduction, en HTML en ligne, ou `null` |
| `translatedSource` | La source pour laquelle la cible a été écrite, ou `null` |

## Empreintes

`Fingerprint::of($text)` est le SHA-256, en hexadécimal, du texte dont chaque suite d'espaces est réduite à une seule
espace, sans espace à l'une ou l'autre extrémité. Deux sources qui ne diffèrent que par les espaces ont la même
empreinte ; un mot, un signe de ponctuation ou une balise la change. `Fingerprint::fromString()` en relit une depuis le
stockage et refuse tout ce qui n'est pas 64 chiffres hexadécimaux en minuscules.

## États

| État | Cible | Utilisable | Sens |
|---|---|---|---|
| `untranslated` | aucune | non | Rien n'est encore écrit |
| `suggested` | oui | non | Proposée par la mémoire, une machine ou une importation ; personne ne l'a acceptée |
| `translated` | oui | oui | Écrite ou acceptée par un traducteur |
| `reviewed` | oui | oui | Traduite, puis vérifiée par un réviseur |
| `stale` | oui | non | Écrite pour une source qui a changé depuis |

`SegmentState::isUsable()` dit si une cible peut être publiée ; `hasTarget()`, si l'état en contient une. Le
constructeur refuse une cible sans état et un état sans cible.

## Transitions

```php
$segment = new Segment('b1/text', 'Hello world');   // untranslated
$segment = $segment->suggest('Bonjour monde');      // suggested
$segment = $segment->translate('Bonjour le monde'); // translated
$segment = $segment->review();                      // reviewed
$segment = $segment->withSource('Hello, world');    // stale: the target and the source it translated are kept
$segment = $segment->confirm();                     // translated again: the target still holds
$segment = $segment->clear();                       // untranslated
```

- `translate()` et `suggest()` règlent la cible pour la source courante, à partir de n'importe quel état.
- `review()` accepte une cible proposée ou traduite, et refuse un segment non traduit ou périmé.
- `confirm()` ramène un segment périmé à l'état traduit, sa cible inchangée ; tout autre état est refusé.
- `withSource()` garde le segment tel quel quand la nouvelle source a la même empreinte, laisse non traduit un segment
  non traduit, rend périmé un segment qui a une cible (en gardant la source pour laquelle sa cible a été écrite), et
  rend de nouveau traduit un segment périmé quand la source revient à ce texte.
- `withKey()` déplace le segment vers une autre clé.

## Ce qui a changé dans une source périmée

`SourceChange` compare, par `phil/text-diff`, les mots de la source pour laquelle une cible a été écrite avec la source
courante. Il donne du HTML échappé avec `<del>` et `<ins>` : l'endroit à regarder avant de réviser la cible.

```php
echo new SourceChange()->of($stale);
// The <del>red</del><ins>blue</ins> door
```

Il donne `null` quand le segment n'a pas de source antérieure, ou quand les mots sont les mêmes et que seules les
balises ont changé.
