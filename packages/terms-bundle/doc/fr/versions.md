<!-- phil-dev source-sha256=7cfc0ab4b81638f4fc7b253d5050f631411fbb84ccf4ecb81e04ed35ff908d37 -->
# Politiques et versions

## Politiques

Une politique est déclarée dans la configuration par sa clé. Une politique **requise** est une politique qu'un membre
connecté doit avoir acceptée pour continuer : la porte la demande. Une politique qui n'est pas requise — un code de
conduite lu une fois, une politique affichée à titre d'information — a ses pages et ses acceptations, et la porte la
laisse tranquille. Une politique peut nommer la licence des contributions des membres ; voir
[Licence des contributions](contribution-licence.md).

## Versions

Une version est publiée une seule fois et n'est jamais modifiée : une correction est une nouvelle version.

| Partie | Règle |
|---|---|
| Numéro | 1 pour la première, puis un de plus que la plus récente, jusqu'à 999999 |
| Textes | Le texte de chaque locale, 32 locales au plus, chacun en UTF-8 valide de 256 Kio au plus et non vide |
| Format | `text` ou `ujo` (UJO (Universal JSON Output), avec `phil/ujo`), vérifié à la publication |
| Publiée le | Le moment de la publication, selon l'horloge |
| En vigueur le | Le moment de l'entrée en vigueur : à la publication ou plus tard ; un moment déjà passé vaut le moment de la publication |
| Importante | Si chaque membre doit l'accepter de nouveau ; la première version l'est toujours |
| Licence | L'identifiant SPDX de la licence des contributions : requis sur la politique qui la nomme, refusé sur les autres |

Les deux moments sont écrits en UTC et relus en UTC, quel que soit le fuseau horaire de l'horloge ou du serveur : un
moment donné dans un autre fuseau est gardé comme l'instant qu'il est.

```php
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\TextFormat;

$terms->publish('privacy', ['en' => $english, 'fr' => $french]);                       // material, in force now
$terms->publish('privacy', ['en' => $fixed], material: false);                        // a correction, asks nothing
$terms->publish('privacy', ['en' => $json], TextFormat::Ujo, new DateTimeImmutable('2026-07-01'));  // announced ahead
```

Un refus est une `InvalidTerms` dont la `reason` est une clé de traduction du domaine `phil_terms`, préfixée par
`error.`, et dont les `parameters` la complètent. Deux publications simultanées ne peuvent pas prendre toutes deux un
numéro : l'index unique refuse la seconde, avec la raison `version_taken`.

## Trois versions à un moment donné

À tout moment, chaque politique a :

- la version **en vigueur** : la plus récente qui est entrée en vigueur — celle qu'affiche sa page et qu'un membre
  accepte ;
- la version **exigée** : la plus récente version *importante* entrée en vigueur — un membre qui l'a acceptée, ou une
  version plus récente, est en règle ;
- la **dernière version importante**, entrée en vigueur ou non — celle dont parle un avis de publication.

Une version publiée avec un moment ultérieur est affichée sur les pages publiques comme à venir et peut être acceptée
d'avance ; la porte la demande une fois qu'elle entre en vigueur. Une version mineure prend la place de la version en
vigueur sans rien demander à ceux qui ont accepté la version exigée ; un membre qui accepte à partir de ce moment
l'accepte elle.

## Textes

Un texte brut est affiché échappé, chacun de ses paragraphes — séparés par une ligne vide — formant un `<p>`, ses
autres sauts de ligne étant conservés. Un document UJO est rendu par `phil/ujo`, qui assainit ce qu'il produit. Une
requête dans une locale pour laquelle la version n'a pas de texte obtient le texte de `default_locale`, sinon le
premier texte de la version.
