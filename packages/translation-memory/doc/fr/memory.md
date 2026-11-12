<!-- phil-dev source-sha256=21604756b78e169fbbe57df567d72e61ef29849ab5c3abe0124e70d9f0169ba7 -->
# Mémoire de traduction

`Memory\TranslationMemory` propose ce qui a déjà été traduit pour une nouvelle source.

```php
$memory = new TranslationMemory($store, minimum: 0.7, limit: 5, shortlist: 50);

foreach ($memory->lookup('The quick brown fox!', LanguageTag::of('en'), LanguageTag::of('fr')) as $match) {
    printf("%s %d%% %s\n", $match->kind->value, $match->percent(), $match->unit->target);
}
// fuzzy 95% Le renard brun rapide
```

## Correspondances

| Sorte | Trouvée par | Score |
|---|---|---|
| `exact` | La même empreinte de source : le même texte, aux espaces près | 1 |
| `fuzzy` | Un texte brut proche | Du minimum à 0,99 |

Une correspondance approximative est trouvée en trois étapes :

1. **Longueurs.** Le score de deux textes est `1 - distance / length of the longer` : un texte de `n` caractères ne
   peut donc atteindre le minimum `m` que contre des textes de `⌈n·m⌉` à `⌊n/m⌋` caractères. Le stockage ne donne que
   ceux-là.
2. **Trigrammes.** Les candidats sont triés par le coefficient de Dice de leurs trigrammes de caractères (lus en
   minuscules, bordés d'une espace), et les `shortlist` meilleurs sont conservés.
3. **Levenshtein.** Chacun reçoit un score selon la distance de Levenshtein entre les points de code des deux textes
   bruts. Quand les deux textes contiennent au plus 256 caractères différents (presque toujours), chaque caractère est
   écrit sur un octet et la fonction `levenshtein()` de PHP compte les modifications. Sinon, la table est calculée dans
   une bande aussi large que la distance que permet le minimum, et abandonnée dès qu'une ligne entière la dépasse.

Les mêmes mots avec d'autres balises obtiennent 0,99, sous toute correspondance exacte. Les correspondances viennent
de la meilleure à la moins bonne, une par texte cible (la meilleure), et au plus `limit`. Un minimum de 1 ne garde que
les correspondances exactes. `MemoryMatch::percent()` donne le score en pourcentage entier arrondi vers le bas, comme
l'affichent les outils de traduction.

Une variante régionale lit aussi les unités de son parent : une recherche pour `fr-CA` donne les unités `fr-CA` et `fr`,
une unité `fr-CA` avant une unité `fr` de même score. Le cinquième argument de la mémoire, `Variants`, nomme les
variantes (`fr-CA` et `en-CA` par défaut, `Variants::none()` pour aucune). Voir [Variantes régionales](variants.md).

`Similarity` est public : `distance()`, `score()`, `trigrams()` et `dice()` servent à toute comparaison de textes.

## Unités et stockages

Une `MemoryUnit` associe une source dans une langue à sa cible dans une autre, et dit d'où elle vient (un document, un
fichier) dans `origin`. Les deux textes sont en HTML en ligne et doivent contenir des mots. L'unité connaît l'empreinte
de sa source, son texte brut et sa longueur en caractères.

Un `MemoryStore` répond à deux questions (`exact()`, les unités d'une paire de langues et d'une empreinte, et
`candidates()`, celles dont la source brute a une longueur comprise entre deux bornes) et conserve les unités avec
`add()`. Une unité de mêmes langues et de même empreinte remplace la précédente : la traduction la plus récente
l'emporte. `InMemoryStore` les garde dans un tableau ; il est dénombrable. Le stockage d'une application sur sa base
de données indexe la paire de langues avec l'empreinte, et avec la longueur.

## Apprendre

```php
$memory->learn($segment, LanguageTag::of('en'), LanguageTag::of('fr'), origin: 'page:42');
```

Un segment est conservé quand il est traduit ou révisé et que sa source et sa cible contiennent toutes deux des mots ;
`learn()` dit s'il l'a été.
