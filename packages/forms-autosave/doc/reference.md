# Reference

## PHP

| Class | Role |
|---|---|
| `PhilFormsAutosaveBundle` | The configuration, the prepended mapping, CSRF token and assets, the compiler pass |
| `Domain\ValueObject\DraftKey` | The opaque name of a draft |
| `Domain\ValueObject\DraftContent` | A structure version and the values of the fields, bounded, to and from JSON |
| `Domain\Model\Draft` | A draft of the server: owner's hash, key, revision, sealed content, saved and expiry times |
| `Domain\Exception\AutosaveException`, `InvalidDraft` | The marker of the bundle's exceptions; what a draft cannot be made of |
| `Application\DraftKeys` | Keys and owners' hashes, derived with the secret |
| `Application\Drafts` | Read, save by revision, discard, purge |
| `Application\DraftCopy` | A draft read and opened |
| `Application\PlainSealer` | Contents stored in clear |
| `Application\Contract\DraftStore`, `DraftOwner`, `DraftSealer` | The ports |
| `Application\Exception\RevisionConflict`, `DraftTooLarge` | A save of another revision; a draft too large |
| `Infrastructure\Form\AutosaveTypeExtension` | The option `autosave` |
| `Infrastructure\Form\FormStructure` | The version of a form's structure |
| `Infrastructure\Form\AutosaveLabels` | The controller's labels, translated |
| `Infrastructure\Http\DraftController`, `DraftRequest`, `Routes` | The endpoints, the check of their requests, the route names |
| `Infrastructure\Http\SubmittedDraft`, `DoneCookie` | Submitted forms kept until the response; their drafts forgotten |
| `Infrastructure\Doctrine\DoctrineDraftStore` | The store in `phil_form_draft` |
| `Infrastructure\Memory\InMemoryDraftStore` | The store in memory, for tests |
| `Infrastructure\KeyManagement\KeyManagementSealer` | Contents sealed through Symfony's KeyManagement component |
| `Infrastructure\Security\TokenDraftOwner` | The user of Symfony Security |
| `Infrastructure\Console\PurgeCommand` | `phil:forms-autosave:purge` |
| `Infrastructure\DependencyInjection\AutosaveServices`, `ServerStorePass` | The services, and their completion |

## Files

| File | Role |
|---|---|
| `config/routes.php` | The routes of the endpoints |
| `templates/status.html.twig` | The live region and the notice |
| `translations/phil_forms_autosave.{en,fr}.php` | The labels |
| `assets/package.json` | The controller, for the StimulusBundle |
| `assets/src/autosave_controller.js` | The controller |
| `assets/src/lib/*.js` | Its modules; see [JavaScript](javascript.md) |
| `assets/styles/forms-autosave.css` | The status and notice's look |
| `tests/Fuzz/draft.php` | The fuzz target of the reader of stored drafts |
