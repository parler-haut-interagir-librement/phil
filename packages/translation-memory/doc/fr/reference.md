<!-- phil-dev source-sha256=8f259e503070ee81ce0a487c5be7af2575119262b780538ef70720dd27a5689d -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\TranslationMemory\LanguageTag` | `final readonly class` | Une étiquette BCP 47 dans sa casse canonique |
| `Phil\TranslationMemory\Text` | `final class` | Les mots du HTML en ligne, espaces normalisées |
| `Phil\TranslationMemory\Fingerprint` | `final readonly class` | Le SHA-256 d'une source normalisée |
| `Phil\TranslationMemory\Variants` | `final readonly class` | Les variantes régionales qui héritent de leur parent, et les segments dont elles héritent |
| `Phil\TranslationMemory\Xml` | `final class`, interne | Le XML d'un fichier d'échange lu en toute sécurité |
| `Phil\TranslationMemory\Segment` | `final readonly class` | Un morceau d'un document dans une langue cible, et ses transitions |
| `Phil\TranslationMemory\SegmentState` | enum | Non traduit, proposé, traduit, révisé ou périmé |
| `Phil\TranslationMemory\SourceSegment` | `final readonly class` | Un morceau d'une source tel qu'un segmenteur le découpe : clé et texte |
| `Phil\TranslationMemory\SourceChange` | `final readonly class` | Ce qui a changé dans la source d'un segment périmé, en HTML |
| `Phil\TranslationMemory\SegmentSync` | `final class` | Les segments enregistrés mis à jour avec un nouveau découpage |
| `Phil\TranslationMemory\SyncResult` | `final readonly class` | Les segments après une synchronisation, et ce qui s'est passé |
| `Phil\TranslationMemory\Segmentation\UjoSegmenter` | `final readonly class` | Un document UJO découpé en segments et réassemblé |
| `Phil\TranslationMemory\Segmentation\Granularity` | enum | Par champ de bloc ou par phrase |
| `Phil\TranslationMemory\Segmentation\TranslatableFields` | `final readonly class` | Les noms de champs traduits et les types de blocs laissés tels quels |
| `Phil\TranslationMemory\Segmentation\SentenceSplitter` | `final readonly class` | Du HTML en ligne découpé en phrases |
| `Phil\TranslationMemory\Memory\TranslationMemory` | `final readonly class` | Les correspondances exactes et approximatives d'une source, et l'apprentissage |
| `Phil\TranslationMemory\Memory\MemoryStore` | interface | L'endroit où les unités de traduction sont conservées |
| `Phil\TranslationMemory\Memory\InMemoryStore` | `final class` | Un stockage dans un tableau |
| `Phil\TranslationMemory\Memory\MemoryUnit` | `final readonly class` | Une source, sa cible, leurs langues et leur origine |
| `Phil\TranslationMemory\Memory\MemoryMatch` | `final readonly class` | Une unité proposée pour une source, son score et sa sorte |
| `Phil\TranslationMemory\Memory\MatchKind` | enum | Exacte ou approximative |
| `Phil\TranslationMemory\Memory\Similarity` | `final class` | Distance de Levenshtein, score, trigrammes, coefficient de Dice |
| `Phil\TranslationMemory\Glossary\Glossary` | `final readonly class` | Les termes d'une paire de langues, trouvés et vérifiés |
| `Phil\TranslationMemory\Glossary\Term` | `final readonly class` | Un terme, ses traductions permises et refusées |
| `Phil\TranslationMemory\Glossary\TermHit` | `final readonly class` | Un terme trouvé dans un texte |
| `Phil\TranslationMemory\Glossary\TermIssue` | `final readonly class` | Un problème d'une cible par rapport à un terme |
| `Phil\TranslationMemory\Glossary\TermIssueKind` | enum | Manquant ou interdit |
| `Phil\TranslationMemory\Glossary\Contract\Inflector` | interface | Les formes fléchies d'une expression |
| `Phil\TranslationMemory\Glossary\RegularPlurals` | `final readonly class` | Les pluriels réguliers de l'anglais et du français |
| `Phil\TranslationMemory\Check\Contract\Check` | interface | Un contrôle de qualité d'un segment traduit |
| `Phil\TranslationMemory\Check\Contract\TextWidth` | interface | La largeur à laquelle une ligne est dessinée, en pixels |
| `Phil\TranslationMemory\Check\Checker` | `final readonly class` | Les contrôles exécutés tour à tour, ceux que les indicateurs écartent omis |
| `Phil\TranslationMemory\Check\Checked` | `final readonly class` | Une cible, sa source, leurs langues et les indicateurs du segment |
| `Phil\TranslationMemory\Check\CheckIssue` | `final readonly class` | Ce qu'un contrôle a trouvé, et son nom |
| `Phil\TranslationMemory\Check\Flags` | `final readonly class` | Les indicateurs d'un segment |
| `Phil\TranslationMemory\Check\Punctuation` | `final readonly class` | La ponctuation finale |
| `Phil\TranslationMemory\Check\Spaces` | `final readonly class` | Les espaces blancs aux deux extrémités, et les doubles espaces |
| `Phil\TranslationMemory\Check\Tags` | `final readonly class` | Les balises de la source |
| `Phil\TranslationMemory\Check\Plurals` | `final readonly class` | Les formes des pluriels ICU |
| `Phil\TranslationMemory\Check\IcuPlurals` | `final class`, interne | Les arguments de pluriel d'un message ICU |
| `Phil\TranslationMemory\Check\SameAsSource` | `final readonly class` | Une cible identique à sa source |
| `Phil\TranslationMemory\Check\MaxSize` | `final readonly class` | La plus grande largeur à laquelle une cible peut être dessinée |
| `Phil\TranslationMemory\Check\AdvanceWidths` | `final readonly class` | Une largeur calculée à partir des chasses d'une police |
| `Phil\TranslationMemory\Check\Glyphs` | `final readonly class` | Les caractères que dessine une police de `phil/fonts` |
| `Phil\TranslationMemory\Xliff\XliffFile` | `final readonly class` | Les langues, l'original et les unités d'un fichier XLIFF |
| `Phil\TranslationMemory\Xliff\XliffUnit` | `final readonly class` | Une unité : clé, source, cible, état, note |
| `Phil\TranslationMemory\Xliff\XliffWriter` | `final readonly class` | Le XLIFF 2.1 écrit |
| `Phil\TranslationMemory\Xliff\XliffReader` | `final readonly class` | Le XLIFF 2.x et 1.2 lu |
| `Phil\TranslationMemory\Xliff\XliffMerge` | `final class` | Un fichier importé fusionné dans les segments |
| `Phil\TranslationMemory\Xliff\MergeResult` | `final readonly class` | Les segments après une fusion, les clés appliquées, les unités ignorées |
| `Phil\TranslationMemory\Xliff\Skipped` | `final readonly class` | Une unité qui n'a rien changé, et pourquoi |
| `Phil\TranslationMemory\Xliff\SkipReason` | enum | Clé inconnue, source changée, pas de cible, inchangée |
| `Phil\TranslationMemory\Xliff\InlineCodes` | `final class`, interne | Les balises du HTML en ligne en suites, limitées à celles d'une source |
| `Phil\TranslationMemory\Tmx\TmxWriter` | `final readonly class` | Des unités de traduction écrites en TMX 1.4b |
| `Phil\TranslationMemory\Tmx\TmxReader` | `final readonly class` | Du TMX lu en unités de traduction |
| `Phil\TranslationMemory\Tbx\TbxWriter` | `final readonly class` | Un glossaire écrit en TBX-Basic |
| `Phil\TranslationMemory\Tbx\TbxReader` | `final readonly class` | Les termes d'une paire de langues lus à partir du TBX |
| `Phil\TranslationMemory\Exception\TranslationMemoryException` | interface | Toute exception de la bibliothèque |
| `Phil\TranslationMemory\Exception\InvalidLanguage` | `final class` | Une étiquette qui n'en est pas une, ou une langue qui ne correspond pas |
| `Phil\TranslationMemory\Exception\InvalidSegment` | `final class` | Un segment, une empreinte ou une unité qui ne peut pas être construit ou changé |
| `Phil\TranslationMemory\Exception\InvalidTerm` | `final class` | Un terme de glossaire sans mots |
| `Phil\TranslationMemory\Exception\InvalidXliff` | `final class` | Un fichier XLIFF illisible |
| `Phil\TranslationMemory\Exception\InvalidTmx` | `final class` | Un fichier TMX illisible |
| `Phil\TranslationMemory\Exception\InvalidTbx` | `final class` | Un fichier TBX illisible |
| `Phil\TranslationMemory\Exception\InvalidConfiguration` | `final class` | Un réglage de la mémoire ou d'un contrôle hors des bornes, ou un indicateur qui n'en est pas un |
