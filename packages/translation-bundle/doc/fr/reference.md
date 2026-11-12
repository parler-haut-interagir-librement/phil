<!-- phil-dev source-sha256=dadcb5cad11579ca8faaef2516ce4781c29086baae06cdf99177f456a49fe644 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\TranslationBundle\PhilTranslationBundle` | bundle | La configuration `phil_translation` et l'association Doctrine |
| `Phil\TranslationBundle\Domain\Model\SegmentRecord` | entité | Un segment d'un document dans une langue cible |
| `Phil\TranslationBundle\Domain\Model\MemoryRecord` | entité | Une unité de la mémoire de traduction |
| `Phil\TranslationBundle\Domain\Model\TermRecord` | entité | Un terme d'un glossaire |
| `Phil\TranslationBundle\Domain\Model\CommentRecord` | entité | Un commentaire sur un segment |
| `Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository` | interface | Le stockage des commentaires |
| `Phil\TranslationBundle\Domain\Repository\SegmentRepository` | interface | Le stockage des segments |
| `Phil\TranslationBundle\Domain\Repository\TermRepository` | interface | Le stockage des termes |
| `Phil\TranslationBundle\Domain\Exception\TranslationBundleException` | interface | Toute exception du bundle |
| `Phil\TranslationBundle\Domain\Exception\InvalidRecord` | `final class` | Une ligne qui ne peut pas être construite ou changée |
| `Phil\TranslationBundle\Application\Translations` | `final readonly class` | La traduction des documents segment par segment |
| `Phil\TranslationBundle\Application\Glossaries` | `final readonly class` | Les glossaires de l'application, et leur TBX |
| `Phil\TranslationBundle\Application\Comments` | `final readonly class` | Les commentaires sur les segments |
| `Phil\TranslationBundle\Application\Credit` | `final readonly class` | Une personne remerciée, et ses totaux |
| `Phil\TranslationBundle\Application\Contract\Translators` | interface | L'utilisateur courant, et le crédit public de chaque traducteur |
| `Phil\TranslationBundle\Application\Contract\MachineTranslator` | interface | Un service de traduction automatique, derrière un adaptateur nommé |
| `Phil\TranslationBundle\Application\Exception\MachineTranslationFailed` | `final class` | Traduction automatique désactivée, en échec ou mal répondue |
| `Phil\TranslationBundle\Application\Progress` | `final readonly class` | Les segments d'un document comptés par état |
| `Phil\TranslationBundle\Application\SourceDocuments` | interface | Les documents de l'application, par référence |
| `Phil\TranslationBundle\Application\SourceDocument` | `final readonly class` | Un document : contenu, langue, titre |
| `Phil\TranslationBundle\Application\TranslationPolicy` | interface | Qui peut traduire, réviser et modifier les glossaires |
| `Phil\TranslationBundle\Application\Exception\NotFound` | `final class` | Un segment, un document, un terme ou un commentaire que les stockages ne contiennent pas |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineSegmentRepository` | `final readonly class` | Les segments dans Doctrine |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineTermRepository` | `final readonly class` | Les termes dans Doctrine |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineMemoryStore` | `final readonly class` | La mémoire dans Doctrine |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineCommentRepository` | `final readonly class` | Les commentaires dans Doctrine |
| `Phil\TranslationBundle\Infrastructure\Memory\InMemoryCommentRepository` | `final class` | Les commentaires dans un tableau |
| `Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository` | `final class` | Les segments dans un tableau |
| `Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository` | `final class` | Les termes dans un tableau |
| `Phil\TranslationBundle\Infrastructure\Http\TranslationController` | `final readonly class` | L'écran de traduction |
| `Phil\TranslationBundle\Infrastructure\Http\GlossaryController` | `final readonly class` | Les pages de glossaire |
| `Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension` | `final readonly class` | La fonction Twig `phil_translation_credits()` |
| `Phil\TranslationBundle\Infrastructure\EasyAdmin\TranslationAdmin` | `final class` | Les liens de menu EasyAdmin vers les pages de glossaire |
| `Phil\TranslationBundle\Infrastructure\Http\Routes` | `final class` | Les noms des routes et la forme des paramètres |
| `Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard` | `final readonly class` | Les formulaires venus d'un autre site refusés, par l'origine de la requête et le jeton CSRF sans état `phil_translation` ; le nom du témoin du jeton |
| `Phil\TranslationBundle\Infrastructure\DependencyInjection\TranslationServices` | `final class` | Les services de la configuration |
| `Phil\TranslationBundle\Infrastructure\DependencyInjection\CsrfProtectionPass` | `final readonly class` | Arrête le conteneur avec un message qui dit quoi changer quand `framework.csrf_protection` vaut false |
| `assets/src/csrf_controller.js`, `assets/src/lib/csrf.js` | contrôleur Stimulus | Un nouveau jeton CSRF dans chaque formulaire de l'écran à son envoi, dans son champ et dans un témoin |
| `templates/glossary.html.twig`, `templates/credits.html.twig` | gabarits Twig | Les pages de glossaire ; le crédit public des traducteurs d'un document |
