<!-- phil-dev source-sha256=b01511045f99d5197369b5d3366c99ed47727e764960f9f9803c06de6f21a930 -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Ses ports sont l'endroit où la mémoire de traduction
est conservée, les contrôles de qualité, la largeur à laquelle une ligne est dessinée et les formes fléchies des termes
du glossaire ; le reste se choisit par les arguments des constructeurs.

## `MemoryStore`

`Phil\TranslationMemory\Memory\MemoryStore` : l'endroit où les unités de traduction sont conservées. `InMemoryStore`
les garde dans un tableau. Voir [Mémoire de traduction](memory.md#unités-et-stockages).

| Méthode | Contrat |
|---|---|
| `exact(LanguageTag $source, LanguageTag $target, Fingerprint $fingerprint): iterable<MemoryUnit>` | Les unités de cette paire de langues dont la source a cette empreinte |
| `candidates(LanguageTag $source, LanguageTag $target, int $minLength, int $maxLength): iterable<MemoryUnit>` | Les unités de cette paire dont la source brute a une longueur comprise entre les deux bornes, en caractères |
| `add(MemoryUnit $unit): void` | Conserve l'unité ; une unité de mêmes langues et de même empreinte remplace la précédente |

Un stockage sur une base de données indexe la paire de langues avec l'empreinte, et avec la longueur : la recherche
approximative ne lit que les candidats qu'on lui donne.

## `Check`

`Phil\TranslationMemory\Check\Contract\Check` : un contrôle de qualité d'un segment traduit. Ceux de la bibliothèque
sont `Punctuation`, `Spaces`, `Tags`, `Plurals`, `SameAsSource`, `MaxSize` et `Glyphs`. Voir
[Contrôles de qualité](checks.md).

| Méthode | Contrat |
|---|---|
| `name(): string` | Des lettres minuscules et des traits d'union ; l'indicateur `ignore-<name>` écarte le contrôle |
| `check(Checked $checked): list<CheckIssue>` | Les problèmes de la cible par rapport à sa source, aucun quand elle passe ; aucun état conservé entre les appels |

## `TextWidth`

`Phil\TranslationMemory\Check\Contract\TextWidth` : la largeur à laquelle une ligne de texte brut est dessinée, en
pixels, pour `MaxSize`. `AdvanceWidths` additionne les chasses d'une police ; la mesure propre à un moteur de rendu (un
moteur de PDF, un navigateur) convient aussi ici.

| Méthode | Contrat |
|---|---|
| `width(string $text): float` | La largeur d'une ligne de texte brut, en pixels |

## `Inflector`

`Phil\TranslationMemory\Glossary\Contract\Inflector` : les formes fléchies d'une expression d'un glossaire.
`RegularPlurals` donne les pluriels réguliers de l'anglais et du français. Voir
[Glossaire](glossary.md#formes-fléchies).

| Méthode | Contrat |
|---|---|
| `forms(string $phrase, LanguageTag $language): list<string>` | Les autres formes de l'expression, sans elle-même ; aucune pour une langue qu'il ne connaît pas |

## Arguments

| Point | Comment |
|---|---|
| Le degré d'approximation d'une correspondance | Le score `$minimum`, la `$limit` de correspondances et la `$shortlist` de candidats évalués, donnés à `TranslationMemory` |
| La façon de découper les documents | La `Granularity` de `UjoSegmenter` : par champ de bloc ou par phrase |
| Les champs traduits | `TranslatableFields` : les noms de champs traduits et les types de blocs laissés tels quels, y compris les blocs propres à l'application |
| L'endroit où les phrases se terminent | Le `SentenceSplitter` donné à `UjoSegmenter`, avec les abréviations qui ne terminent aucune phrase |
| Les termes d'une paire de langues | Les `Term` donnés à `Glossary`, tirés du stockage de l'application, et leurs formes |
| Les variantes régionales | Les `Variants` donnés à `TranslationMemory`, et utilisés pour hériter des segments : `fr-CA` et `en-CA` par défaut |
| Les contrôles exécutés | Les `Check` donnés à `Checker`, ou `Checker::defaults()` avec un `TextWidth`, une limite et une police |
| Les contrôles d'un segment | Ses `Flags` : `ignore-<check>`, `max-size:<pixels>` |
