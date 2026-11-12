<!-- phil-dev source-sha256=d1270fd8b538ad77159463887bc19e93ce19dadadba015a24c7cc0c80d459f87 -->
# Variantes régionales

Une variante régionale se traduit comme un ensemble d'écarts par rapport à sa langue parente. Un segment `fr-CA` sans
cible utilisable qui lui soit propre prend celle de `fr` : les traducteurs du français canadien n'écrivent que les
segments qui diffèrent au Canada (`courriel` pour `e-mail`, `fin de semaine` pour `week-end`), et tous les autres
segments se lisent en français.

`Variants` nomme les variantes qui héritent. Par défaut, ce sont `fr-CA` et `en-CA` ; toute autre étiquette est
autonome.

```php
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Variants;

$variants = new Variants();                          // fr-CA and en-CA
$variants = new Variants(['fr-CA', 'en-CA', 'pt-BR']);
$variants = Variants::none();                        // every language stands alone

$variants->parentOf(LanguageTag::of('fr-CA'));       // fr
$variants->chain(LanguageTag::of('fr-CA'));          // [fr-CA, fr]
$variants->variants();                               // ['fr-CA', 'en-CA']
```

Une locale écrite à la façon de Symfony, `fr_CA`, se lit avec `LanguageTag::fromLocale()`. Le parent d'une étiquette est
l'étiquette sans sa dernière sous-étiquette : `LanguageTag::of('fr-CA')->parent()` est `fr`, et `zh-Hant-TW` donne
`zh-Hant`. Une variante dont le parent est lui-même une variante hérite le long de la chaîne, la langue la plus proche
l'emportant. Une langue primaire seule n'a pas de parent et est refusée comme variante avec `InvalidLanguage`.

## Hériter des cibles

`inherit($own, $parent)` donne les segments d'une variante avec les cibles de son parent remplies :

- un segment de la variante qui est traduit ou révisé garde sa propre cible ;
- celui qui est non traduit, suggéré ou périmé, ou qui manque, prend le segment du parent de même clé, quand celui-ci
  est traduit ou révisé et qu'il a été fait à partir de la même source, aux espaces blancs près ;
- les segments propres à la variante viennent d'abord, dans leur ordre, puis les segments utilisables du parent qui lui
  manquent.

```php
$french = $segmenter->assemble($document, $variants->inherit($canadianSegments, $frenchSegments));
```

Pour une chaîne plus longue, résolvez en partant de la langue la plus éloignée : les segments du parent donnés à
`inherit()` sont ceux du parent déjà résolus contre son propre parent. `phil/translation-bundle` le fait pour le
document qu'un site publie, et garde les segments propres à la variante pour l'écran des traducteurs.

## La mémoire

`TranslationMemory` lit selon la même chaîne : une recherche pour `fr-CA` donne les unités `fr-CA` et les unités `fr`,
le meilleur score d'abord, et une unité `fr-CA` avant une unité `fr` de même score. La mémoire reçoit ses `Variants`
comme cinquième argument, `new Variants()` par défaut. Voir [Mémoire de traduction](memory.md).
