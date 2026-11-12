<!-- phil-dev source-sha256=c0303c803d12b9f94ab490233b52946d80007d778485181ea4c54c2a244aeb35 -->
# Configuration

```yaml
# config/packages/phil_translation.yaml
phil_translation:
    store: doctrine            # or "memory", for tests
    entity_manager: default
    granularity: block         # or "sentence"
    prefill: true
    min_score: 0.7
    max_suggestions: 5
    sources: ~                 # a SourceDocuments service
    policy: ~                  # a TranslationPolicy service
    layout: '@PhilTranslation/layout.html.twig'
    variants: [fr-CA, en-CA]   # the regional variants that inherit from their parent
    translators: ~             # a Translators service
    inflector: ~               # an Inflector service, such as Phil\TranslationMemory\Glossary\RegularPlurals
    machine_translation:
        enabled: false
        translator: ~          # a MachineTranslator service
```

| Option | Valeur par défaut | Sens |
|---|---|---|
| `store` | `doctrine` | L'endroit où les segments, les termes et la mémoire sont conservés : Doctrine, ou des tableaux PHP qui durent une requête, pour les tests |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine des tables |
| `granularity` | `block` | La façon de découper les documents : un segment par champ texte d'un bloc, ou un par phrase |
| `prefill` | `true` | Si les correspondances exactes de la mémoire sont proposées aux nouveaux segments, comme `suggested` |
| `min_score` | `0.7` | Le plus bas score d'une correspondance approximative, de 0,01 à 1 ; 1 ne garde que les correspondances exactes |
| `max_suggestions` | `5` | Le plus grand nombre de correspondances proposées pour un segment, au moins 1 |
| `sources` | aucune | Le service, un `SourceDocuments`, qui trouve les documents à traduire ; l'écran en a besoin |
| `policy` | aucune | Le service, une `TranslationPolicy`, qui dit qui peut traduire et réviser ; l'écran en a besoin |
| `layout` | `@PhilTranslation/layout.html.twig` | La mise en page Twig que l'écran et les pages de glossaire étendent ; elle définit les blocs `title` et `content` |
| `variants` | `[fr-CA, en-CA]` | Les variantes régionales, sous forme d'étiquettes BCP 47 ayant un parent, qui héritent des cibles de leur langue parente ; voir [Variantes régionales](variants.md) |
| `translators` | aucune | Le service, un `Translators`, qui nomme l'utilisateur courant et le crédit public de chaque traducteur ; voir [Commentaires et crédits](collaboration.md) |
| `inflector` | aucune | Le service, un `Inflector`, dont les formes sont ajoutées par les glossaires à leurs termes |
| `machine_translation.enabled` | `false` | Si la traduction automatique est activée ; voir [Traduction automatique](machine-translation.md) |
| `machine_translation.translator` | aucune | Le service, un `MachineTranslator`, un adaptateur qui porte le nom de son service ; nécessaire quand `enabled` est vrai |

Changer `granularity` sur des documents déjà préparés change toutes les clés : leurs segments sont préparés de
nouveau, les anciens sont retirés, et les nouveaux reçoivent comme propositions ce que la mémoire a appris des anciens.

## Services

| Service | Public | Rôle |
|---|---|---|
| `Phil\TranslationBundle\Application\Translations` | oui | La traduction des documents |
| `Phil\TranslationBundle\Application\Glossaries` | oui | Les glossaires |
| `Phil\TranslationBundle\Application\Comments` | oui | Les commentaires sur les segments |
| `Phil\TranslationMemory\Memory\TranslationMemory` | oui | La mémoire, pour les recherches propres à l'application |
| `Phil\TranslationBundle\Infrastructure\Http\TranslationController` | oui | L'écran |
| `Phil\TranslationBundle\Infrastructure\Http\GlossaryController` | oui | Les pages de glossaire |
| `Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension` | non | La fonction Twig `phil_translation_credits()` |
| `Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository` | alias | Le stockage des commentaires |
| `Phil\TranslationMemory\Variants` | non | Les variantes régionales de la configuration |
| `Phil\TranslationMemory\Glossary\RegularPlurals` | non | Les pluriels réguliers de l'anglais et du français, pour `inflector` |
| `Phil\TranslationBundle\Domain\Repository\SegmentRepository` | alias | Le stockage des segments |
| `Phil\TranslationBundle\Domain\Repository\TermRepository` | alias | Le stockage des termes |
| `Phil\TranslationMemory\Memory\MemoryStore` | alias | Le stockage de la mémoire |
| `Phil\TranslationMemory\Segmentation\UjoSegmenter` | non | Le segmenteur de la granularité configurée |
