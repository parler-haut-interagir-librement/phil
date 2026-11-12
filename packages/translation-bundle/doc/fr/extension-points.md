<!-- phil-dev source-sha256=bb6a1e2dde0f48d9da1763cd293feaa3abcb8a055a943cf0a625f50d2e6f9ff1 -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou surcharge. Les segments, la mémoire et les glossaires sont
ceux de `phil/translation-memory`, et les documents sont en UJO.

## Ports écrits par l'application

Chacun est nommé par son identifiant de service dans la configuration. L'écran répond 404 tant que `sources` et `policy`
ne sont pas réglés. Voir [L'écran de traduction](screen.md#les-services-de-lapplication).

### `SourceDocuments`

`Phil\TranslationBundle\Application\SourceDocuments` : trouve les documents à traduire (`sources`).

| Méthode | Contrat |
|---|---|
| `find(string $document): ?SourceDocument` | Le contenu UJO du document, sa langue et son titre, ou `null` pour un document qu'il ne connaît pas |

### `TranslationPolicy`

`Phil\TranslationBundle\Application\TranslationPolicy` : qui peut traduire et réviser, pour l'utilisateur courant
(`policy`).

| Méthode | Contrat |
|---|---|
| `canTranslate(string $document, LanguageTag $targetLanguage): bool` | Voir la page, écrire et proposer des cibles, les confirmer et les vider, exporter et importer ; voir les unités de la mémoire apprises de ce document |
| `canReview(string $document, LanguageTag $targetLanguage): bool` | Marquer des cibles comme révisées, par l'écran ou par un fichier XLIFF importé ; supprimer des commentaires |
| `canEditGlossary(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): bool` | Ajouter, changer et supprimer les termes de ce glossaire, l'exporter et l'importer |

### `Translators`

`Phil\TranslationBundle\Application\Contract\Translators` : qui est à l'écran, et comment chaque personne est remerciée
(`translators`). Facultatif. Voir [Commentaires et crédits](collaboration.md#le-service-translators).

| Méthode | Contrat |
|---|---|
| `current(): ?string` | L'identifiant de l'utilisateur courant, d'au plus 255 octets, conservé avec ce qu'il écrit ; `null` quand il est inconnu |
| `credit(string $translator): ?string` | Le nom sous lequel une personne est créditée en public ; `null` quand elle a demandé de ne pas l'être |

### `MachineTranslator`

`Phil\TranslationBundle\Application\Contract\MachineTranslator` : un service de traduction automatique, derrière un
adaptateur qui porte son nom (`machine_translation.translator`). Facultatif, et désactivé par défaut. Voir
[Traduction automatique](machine-translation.md).

| Méthode | Contrat |
|---|---|
| `name(): string` | Le nom du service |
| `translate(list<string> $sources, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): list<string>` | Une cible par source, dans l'ordre |

### `Inflector`

`Phil\TranslationMemory\Glossary\Contract\Inflector` : les formes fléchies des termes d'un glossaire (`inflector`), venu
de `phil/translation-memory`. Facultatif ; `RegularPlurals` est enregistré pour lui.

## Stockages

`SegmentRepository`, `TermRepository`, `Contract\CommentRepository` et `Phil\TranslationMemory\Memory\MemoryStore` sont
des alias des stockages que choisit `store` : Doctrine, ou la mémoire pour les tests. Une application les fait pointer
vers ses propres services, le stockage de la mémoire étant indexé sur ses langues, son empreinte et sa longueur. Voir [Stockage](storage.md).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `granularity` | Des segments par champ de bloc ou par phrase |
| `prefill`, `min_score`, `max_suggestions` | Ce que propose la mémoire |
| `layout` | Le gabarit que l'écran étend, avec les blocs `title` et `content` : le gabarit de base de l'application, avec son application Stimulus |
| `store`, `entity_manager` | L'endroit où les segments, les termes, les commentaires et la mémoire sont conservés |
| `variants` | Les variantes régionales qui héritent de leur parent |

Voir [Configuration](configuration.md).

## Services et écran

| Point | Comment |
|---|---|
| Traduire dans le code | `Translations`, `Glossaries` et `Comments`, publics ; voir [Traduire des documents](translations.md) |
| Les contrôles exécutés | Un `Checker` donné à `Translations` comme dernier argument, dans la définition de service propre à l'application |
| Le crédit sur une page publiée | La fonction Twig `phil_translation_credits()` et `@PhilTranslation/credits.html.twig` |
| Une interface d'administration EasyAdmin | `TranslationAdmin::menuItems()` ; voir [Pages de glossaire](glossary-pages.md#easyadmin) |
| Recherches dans la mémoire propres à l'application | `Phil\TranslationMemory\Memory\TranslationMemory`, public |
| L'apparence de l'écran | Les classes sous `<main class="phil-translation">` : `progress`, `state state-<state>`, `terms`, `issues`, `suggestions` et les autres |
