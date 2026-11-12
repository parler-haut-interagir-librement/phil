# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\TranslationBundle\PhilTranslationBundle` | bundle | The configuration `phil_translation` and the Doctrine mapping |
| `Phil\TranslationBundle\Domain\Model\SegmentRecord` | entity | A segment of a document in a target language |
| `Phil\TranslationBundle\Domain\Model\MemoryRecord` | entity | A unit of the translation memory |
| `Phil\TranslationBundle\Domain\Model\TermRecord` | entity | A term of a glossary |
| `Phil\TranslationBundle\Domain\Model\CommentRecord` | entity | A comment on a segment |
| `Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository` | interface | The store of comments |
| `Phil\TranslationBundle\Domain\Repository\SegmentRepository` | interface | The store of segments |
| `Phil\TranslationBundle\Domain\Repository\TermRepository` | interface | The store of terms |
| `Phil\TranslationBundle\Domain\Exception\TranslationBundleException` | interface | Every exception of the bundle |
| `Phil\TranslationBundle\Domain\Exception\InvalidRecord` | `final class` | A row that cannot be built or changed |
| `Phil\TranslationBundle\Application\Translations` | `final readonly class` | The translation of documents segment by segment |
| `Phil\TranslationBundle\Application\Glossaries` | `final readonly class` | The glossaries of the application, and their TBX |
| `Phil\TranslationBundle\Application\Comments` | `final readonly class` | The comments on segments |
| `Phil\TranslationBundle\Application\Credit` | `final readonly class` | A person thanked, and their counts |
| `Phil\TranslationBundle\Application\Contract\Translators` | interface | The current user, and the public credit of each translator |
| `Phil\TranslationBundle\Application\Contract\MachineTranslator` | interface | A machine translation service, behind a named adapter |
| `Phil\TranslationBundle\Application\Exception\MachineTranslationFailed` | `final class` | Machine translation off, failed, or wrongly answered |
| `Phil\TranslationBundle\Application\Progress` | `final readonly class` | The segments of a document counted by state |
| `Phil\TranslationBundle\Application\SourceDocuments` | interface | The application's documents, by reference |
| `Phil\TranslationBundle\Application\SourceDocument` | `final readonly class` | A document: content, language, title |
| `Phil\TranslationBundle\Application\TranslationPolicy` | interface | Who may translate, review and edit glossaries |
| `Phil\TranslationBundle\Application\Exception\NotFound` | `final class` | A segment, document, term or comment the stores do not hold |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineSegmentRepository` | `final readonly class` | The segments in Doctrine |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineTermRepository` | `final readonly class` | The terms in Doctrine |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineMemoryStore` | `final readonly class` | The memory in Doctrine |
| `Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineCommentRepository` | `final readonly class` | The comments in Doctrine |
| `Phil\TranslationBundle\Infrastructure\Memory\InMemoryCommentRepository` | `final class` | The comments in an array |
| `Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository` | `final class` | The segments in an array |
| `Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository` | `final class` | The terms in an array |
| `Phil\TranslationBundle\Infrastructure\Http\TranslationController` | `final readonly class` | The translation screen |
| `Phil\TranslationBundle\Infrastructure\Http\GlossaryController` | `final readonly class` | The glossary pages |
| `Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension` | `final readonly class` | The Twig function `phil_translation_credits()` |
| `Phil\TranslationBundle\Infrastructure\EasyAdmin\TranslationAdmin` | `final class` | The EasyAdmin menu links to the glossary pages |
| `Phil\TranslationBundle\Infrastructure\Http\Routes` | `final class` | The route names and parameter shapes |
| `Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard` | `final readonly class` | Forms from another site refused, by the request's origin and the stateless CSRF token `phil_translation`; the name of the token's cookie |
| `Phil\TranslationBundle\Infrastructure\DependencyInjection\TranslationServices` | `final class` | The services of the configuration |
| `Phil\TranslationBundle\Infrastructure\DependencyInjection\CsrfProtectionPass` | `final readonly class` | Stops the container with a message that says what to change when `framework.csrf_protection` is false |
| `assets/src/csrf_controller.js`, `assets/src/lib/csrf.js` | Stimulus controller | A new CSRF token in each form of the screen as it is sent, in its field and in a cookie |
| `templates/glossary.html.twig`, `templates/credits.html.twig` | Twig templates | The glossary pages; the public credit of a document's translators |
