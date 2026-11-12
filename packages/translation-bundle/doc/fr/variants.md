<!-- phil-dev source-sha256=5ba3d284592d0eca60f479c24f2ee8f5280bf1f911bdd4c45563a7b3421bd097 -->
# Variantes régionales

Une variante régionale se traduit comme les écarts par rapport à sa langue parente : le français canadien n'écrit que
les segments qui diffèrent du français (`courriel`, `fin de semaine`) et lit tous les autres en français. L'option
`variants` nomme les variantes, sous forme d'étiquettes BCP 47 ; par défaut `fr-CA` et `en-CA`.

```yaml
phil_translation:
    variants: [fr-CA, en-CA]     # [] for none: every language stands alone
```

Une locale écrite à la façon de Symfony, `fr_CA`, se lit avec `LanguageTag::fromLocale($request->getLocale())`.

## La chaîne

Chaque variante a une chaîne : elle-même, son parent et la source. Un document `fr-CA` se lit d'abord dans ses propres
segments, puis dans les segments `fr` là où `fr-CA` n'a pas de cible utilisable, puis dans la langue source là où
aucun des deux n'en a. Une cible du parent n'est prise que si elle est traduite ou révisée et qu'elle a été faite à
partir du même texte source : une cible `fr` écrite pour une source plus ancienne n'est pas héritée.

La mémoire lit selon la même chaîne : à un segment `fr-CA`, on propose d'abord les unités `fr-CA` puis les unités `fr`,
et un nouveau segment `fr-CA` est prérempli avec la correspondance exacte `fr` quand il n'en a pas de propre.

## Deux lectures

| Lecture | Méthode | Pour |
|---|---|---|
| Traduite, par la chaîne | `translated($document, $source, $language)` | Le site qui publie le document |
| Brute, les segments propres de la langue | `raw($document, $source, $language)` | L'administration, pour voir ce qu'une variante change |

```php
$published = $translations->translated('article:42', $article->content(), LanguageTag::fromLocale('fr_CA'));
$canadianOnly = $translations->raw('article:42', $article->content(), LanguageTag::of('fr-CA'));
```

`resolved($document, $language)` donne les segments qu'utilise la lecture traduite : ceux de la variante, avec les
cibles utilisables de sa chaîne là où elle n'en a pas. `parentOf($language)` nomme le parent d'une variante, `null`
pour une langue qui n'en est pas une.

Pour une langue qui n'est pas une variante, les deux lectures sont identiques : ses propres segments, puis la source.

## À l'écran

La page d'une variante dit de quelle langue elle hérite, et chaque segment sans cible utilisable qui lui soit propre
montre la cible qu'il hérite (`From fr: …`), de sorte que le traducteur n'écrit une cible `fr-CA` que là où le Canada
diffère.
