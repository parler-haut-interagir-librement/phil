<!-- phil-dev source-sha256=27efe997907edd7842a41345a031048d058adb1fc1eef07670c0bd1e2eea0b74 -->
# Référence

## PHP

| Classe | Rôle |
|---|---|
| `PhilFormsAutosaveBundle` | La configuration, le mappage, le jeton CSRF et les ressources ajoutés en préfixe, la passe de compilation |
| `Domain\ValueObject\DraftKey` | Le nom opaque d'un brouillon |
| `Domain\ValueObject\DraftContent` | Une version de structure et les valeurs des champs, bornées, vers JSON et depuis JSON |
| `Domain\Model\Draft` | Un brouillon du serveur : empreinte du propriétaire, clé, révision, contenu scellé, dates d'enregistrement et d'expiration |
| `Domain\Exception\AutosaveException`, `InvalidDraft` | Le marqueur des exceptions du bundle ; ce dont on ne peut pas faire un brouillon |
| `Application\DraftKeys` | Les clés et les empreintes des propriétaires, dérivées avec le secret |
| `Application\Drafts` | Lire, enregistrer par révision, abandonner, purger |
| `Application\DraftCopy` | Un brouillon lu et ouvert |
| `Application\PlainSealer` | Des contenus stockés en clair |
| `Application\Contract\DraftStore`, `DraftOwner`, `DraftSealer` | Les ports |
| `Application\Exception\RevisionConflict`, `DraftTooLarge` | Un enregistrement d'une autre révision ; un brouillon trop gros |
| `Infrastructure\Form\AutosaveTypeExtension` | L'option `autosave` |
| `Infrastructure\Form\FormStructure` | La version de la structure d'un formulaire |
| `Infrastructure\Form\AutosaveLabels` | Les libellés du contrôleur, traduits |
| `Infrastructure\Http\DraftController`, `DraftRequest`, `Routes` | Les points de terminaison, la vérification de leurs requêtes, les noms des routes |
| `Infrastructure\Http\SubmittedDraft`, `DoneCookie` | Les formulaires soumis gardés jusqu'à la réponse ; leurs brouillons oubliés |
| `Infrastructure\Doctrine\DoctrineDraftStore` | Le stockage dans `phil_form_draft` |
| `Infrastructure\Memory\InMemoryDraftStore` | Le stockage en mémoire, pour les tests |
| `Infrastructure\KeyManagement\KeyManagementSealer` | Des contenus scellés par le composant KeyManagement de Symfony |
| `Infrastructure\Security\TokenDraftOwner` | L'utilisateur de Symfony Security |
| `Infrastructure\Console\PurgeCommand` | `phil:forms-autosave:purge` |
| `Infrastructure\DependencyInjection\AutosaveServices`, `ServerStorePass` | Les services et leur complément |

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/routes.php` | Les routes des points de terminaison |
| `templates/status.html.twig` | La région active et l'avis |
| `translations/phil_forms_autosave.{en,fr}.php` | Les libellés |
| `assets/package.json` | Le contrôleur, pour le StimulusBundle |
| `assets/src/autosave_controller.js` | Le contrôleur |
| `assets/src/lib/*.js` | Ses modules ; voir [JavaScript](javascript.md) |
| `assets/styles/forms-autosave.css` | L'apparence de l'état et de l'avis |
| `tests/Fuzz/draft.php` | La cible de fuzzing du lecteur des brouillons stockés |
