<!-- phil-dev source-sha256=32e9080d7b925de7b289975ae3b1dcd60d25515c7de767ca5bb51d9bbe664017 -->
# Référence

| Type | Rôle |
|---|---|
| `PhilTermsBundle` | Configure `phil_terms` |
| `Domain\Identifiers` | Les formes des clés de politique, des identifiants de membre, des locales et des licences |
| `Domain\TextFormat` | Texte brut ou UJO (Universal JSON Output) |
| `Domain\AcceptanceChannel` | Inscription, formulaire, API, importation |
| `Domain\ValueObject\Policy` | Une politique déclarée, si elle est requise, son renouvellement, si elle nomme la licence des contributions |
| `Domain\ValueObject\PolicyVersion` | Une version publiée, jamais modifiée, avec sa licence des contributions s'il y en a une |
| `Domain\ValueObject\Acceptance` | L'acceptation d'une version par un membre |
| `Domain\Contract\PolicyVersionRepository` | Le port des versions |
| `Domain\Contract\AcceptanceRepository` | Le port des acceptations |
| `Domain\Exception\InvalidTerms` | Une valeur ou une opération refusée, avec sa raison |
| `Application\Policies` | Les politiques déclarées, dans l'ordre |
| `Application\VersionCatalog` | Les versions de chaque politique pour la requête |
| `Application\Terms` | Publie une version, enregistre une acceptation |
| `Application\AcceptanceChecker` | Ce qu'un membre doit accepter |
| `Application\Outstanding` | Les membres qui ne sont pas en règle avec une politique |
| `Application\ContributionLicences` | La licence de la contribution d'un membre à un moment donné |
| `Application\ContributionLicence` | Une licence, la version acceptée et son moment |
| `Application\Contract\MemberResolver` | Le port du membre courant |
| `Application\Contract\MemberSource` | Le port de tous les membres |
| `Application\Contract\TermsNotifier` | Le port des avis et des rappels |
| `Application\Contract\AddressMasker` | Le port de ce qui est conservé d'une adresse |
| `Application\Contract\PolicyTexts` | Le port des textes : vérifiés, affichés, comparés |
| `Application\Event\VersionPublished`, `TermsAccepted` | Les événements, des identifiants seulement |
| `Application\Message\SendTermsReminders` | L'exécution planifiée des rappels |
| `Application\Handler\SendTermsRemindersHandler` | Envoie les rappels |
| `Infrastructure\DependencyInjection\TermsServices` | Câble les services de la configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les recettes du kit d'après le sous-répertoire des composants du Toolkit |
| `Infrastructure\Doctrine\TermsTables` | Les deux tables |
| `Infrastructure\Doctrine\TermsSchemaListener` | Les ajoute au schéma de l'ORM |
| `Infrastructure\Doctrine\DbalPolicyVersions`, `DbalAcceptances` | Les deux stockages avec DBAL, leurs moments en UTC |
| `Infrastructure\Doctrine\RowReader` | Les valeurs d'une ligne, lues strictement |
| `Infrastructure\Http\AcceptanceGate` | Arrête un membre qui a une version à accepter |
| `Infrastructure\Http\AcceptanceController` | La page d'acceptation |
| `Infrastructure\Http\PolicyController` | Les pages publiques et les changements |
| `Infrastructure\Http\TermsAdminController` | L'administration |
| `Infrastructure\Http\Pages` | Les gabarits, le jeton, l'adresse de retour |
| `Infrastructure\Form\TermsAcceptanceTypeExtension` | L'option `terms_acceptance` d'un formulaire |
| `Infrastructure\Form\SignupAcceptances` | Conserve les acceptations d'un formulaire d'inscription |
| `Infrastructure\Console\OutstandingCommand` | `phil:terms:outstanding` |
| `Infrastructure\Console\LegalLinksCommand` | `phil:terms:check-legal-links` |
| `Infrastructure\Scheduler\TermsSchedule` | L'horaire `phil_terms` |
| `Infrastructure\Notifications\NotificationsNotifier` | Les avis par `phil/notifications-bundle` |
| `Infrastructure\Notifications\OutstandingAudience` | L'audience `phil_terms:<policy>` |
| `Infrastructure\Privacy\AcceptancesPrivacy` | L'exportation et l'effacement de `phil/privacy-bundle` |
| `Infrastructure\Security\TokenMemberResolver` | Le membre du jeton de sécurité, avec `stable_identifiers` déclaré ; jamais une adresse courriel |
| `Infrastructure\IpAnonymizer\NetworkMasker` | Le réseau d'une adresse |
| `Infrastructure\Text\PolicyTextRenderer` | Les textes en HTML et leurs changements |
| `Infrastructure\Twig\TermsTwig` | `phil_terms_kit()`, le nom d'une recette du kit, pour les gabarits |
| `Infrastructure\Twig\KitComponents` | Les noms sous lesquels les recettes du kit sont installées |
| `Infrastructure\EasyAdmin\TermsAdmin` | Le lien d'un menu EasyAdmin |
