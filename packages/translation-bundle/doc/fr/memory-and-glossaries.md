<!-- phil-dev source-sha256=b116d3f6cbf29f51732e35c0de2694b8b3f5ba7f47ee674df42ba6a051bb7433 -->
# Mémoire et glossaires

## La mémoire

Le bundle conserve une seule mémoire de traduction pour toute l'application, dans la table `phil_translation_memory` :
une unité par paire de langues et par empreinte de source, la traduction la plus récente l'emportant. Une variante régionale lit les unités de
son parent après les siennes. Chaque cible qui devient utilisable (traduite, révisée, confirmée, ou importée comme
telle) est apprise, avec le document pour origine. La mémoire la propose ensuite :

- aux nouveaux segments de tout document, comme `suggested`, quand la source est la même, aux espaces près
  (`prefill`) ;
- à l'écran, pour tout segment pas encore utilisable, exacte ou proche, jusqu'à `min_score`, au plus
  `max_suggestions`.

Avec une `policy`, les deux propositions ne gardent que les unités apprises d'un document que l'utilisateur courant
peut traduire (`canTranslate()` sur l'origine de l'unité et la langue cible), et celles apprises d'aucun document,
remplies par l'application avec `learn()` sans origine : la traduction d'un document ne paraît jamais sur l'écran d'un
utilisateur qui ne peut pas voir ce document. Une application qui donne d'autres origines aux unités qu'elle apprend en
répond aussi dans sa politique.

`Phil\TranslationMemory\Memory\TranslationMemory` est un service public : une application peut consulter elle-même la
mémoire, ou la remplir à partir d'une mémoire de traduction existante avec `learn()`, ou par son stockage, l'alias
`MemoryStore`.

## Glossaires

`Phil\TranslationBundle\Application\Glossaries` conserve un glossaire par paire de langues, dans la table
`phil_translation_term` :

```php
$glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['points de vie', 'PV'], forbidden: ['vie'], note: 'Always plural.'));
$glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('Dungeon Master'));   // kept as it is
$glossaries->remove(LanguageTag::of('en'), LanguageTag::of('fr'), 'hit points');
```

`put()` ajoute un terme, ou donne à un terme que le glossaire a déjà ses nouvelles traductions, sa casse, sa note et ses
formes ; `remove()` lance `NotFound` pour un terme qu'il n'a pas ; `export()` et `import()` échangent le glossaire en
TBX. Une variante régionale a ses propres glossaires (`en` vers `fr-CA`), que les pages de glossaire modifient comme
tous les autres ([Pages de glossaire](glossary-pages.md)). `terms()` liste les lignes d'une paire de langues dans
l'ordre alphabétique, et `glossary()` donne le `Glossary` de la bibliothèque, dont l'écran se sert pour montrer les
termes de chaque segment et les problèmes de sa cible. La documentation du glossaire de `phil/translation-memory` dit
comment les termes correspondent.
