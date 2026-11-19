<!-- phil-dev source-sha256=b678bee6379a168cab599de23415e94fbf787e216ae6a4f6205005412d6da6df -->
# Référence

Chaque type du bundle, avec son rôle.

| Type | Rôle |
|---|---|
| `PhilLibraryBundle` | Câble les services, associe les entités, configure `phil_library` |
| `Domain\Model\Title` | Un titre du catalogue et sa fiche |
| `Domain\Model\Copy` | Un objet d'un titre, prêté ; son code à barres, son état, sa note et son article de stockroom |
| `Domain\Model\Loan` | Un exemplaire prêté à un membre pour une période |
| `Domain\ValueObject\Record` | Ce qui décrit un titre ; son ISBN conservé en ISBN-13 |
| `Domain\ValueObject\Member` | La forme de l'identifiant stable d'un membre |
| `Domain\Status\TitleKind` | Jeu, livre, autre |
| `Domain\Status\CopyCondition` | Bon, usé, endommagé, incomplet, retiré |
| `Domain\Status\LoanStatus` | Réservé, actif, retourné, annulé, perdu |
| `Domain\Contract\TitleRepository`, `CopyRepository`, `LoanRepository` | Les ports des titres, des exemplaires et des prêts |
| `Domain\Event\CopyLent`, `CopyReturned` | Les événements d'un exemplaire remis et rendu, avec des identifiants seulement |
| `Domain\Exception\InvalidLibraryData` | Une valeur ou un changement refusé |
| `Application\Catalogue` | Les titres, les fiches, les exemplaires, les états, les liens avec stockroom |
| `Application\Loans` | Prête, réserve, enregistre la sortie, renouvelle, reprend, annule, perd ; oublie l'historique |
| `Application\LoanPolicy` | Les règles des prêts, et un exemplaire tel que `phil/reservation` le voit |
| `Application\Reminders` | Envoie les rappels et les avis dus |
| `Application\Search\SearchCriteria`, `SearchResult`, `TitleRow` | Une recherche dans le catalogue et une page de celle-ci |
| `Application\Contract\TitleSearch` | Le port de la recherche |
| `Application\Contract\StockLink` | Le port des mouvements de stock |
| `Application\Contract\ReminderSender` | Le port qui envoie un rappel |
| `Application\Contract\MemberResolver` | Le port qui nomme le membre d'une requête |
| `Application\Message\SendLoanReminder` | Le message Messenger d'un rappel |
| `Application\Exception\LoanRefused` | Un refus, avec sa raison et ses paramètres |
| `Application\Exception\NotFound` | Un titre, un exemplaire ou un prêt manquant, ou un exemplaire qui n'est pas sorti |
| `Infrastructure\Doctrine\DoctrineTitleRepository`, `DoctrineCopyRepository`, `DoctrineLoanRepository` | Les dépôts avec Doctrine |
| `Infrastructure\Doctrine\DbalTitleSearch` | La recherche avec DBAL |
| `Application\Contract\LoanTransaction` | Le port qui exécute un prêt et son mouvement de stock dans une seule transaction |
| `Infrastructure\Doctrine\DbalLoanTransaction` | Cette transaction sur la connexion DBAL de la bibliothèque, câblée quand `phil/stockroom-bundle` la partage |
| `Infrastructure\Stockroom\StockroomLink` | Les mouvements de stock par `phil/stockroom`, dans les transactions de `phil/stockroom-bundle` quand il est installé |
| `Infrastructure\Stockroom\NoStockLink` | Aucune réserve : rien ne bouge |
| `Infrastructure\Messenger\MessengerReminderSender` | Envoie chaque rappel sous forme de message |
| `Infrastructure\Privacy\LoanHistoryPrivacy` | L'historique des prêts d'un membre dans les demandes de vie privée |
| `Infrastructure\Console\SendRemindersCommand` | `phil:library:send-reminders` |
| `Infrastructure\Console\ForgetHistoryCommand` | `phil:library:forget-history` |
| `Infrastructure\Form\SearchType`, `LendType`, `ReturnType`, `TitleType`, `CopyType`, `TitleData` | Les formulaires des pages |
| `Infrastructure\Http\CatalogueController`, `DeskController`, `AdminController`, `MemberLoansController`, `Pages` | Les pages |
| `Infrastructure\Twig\LibraryExtension`, `KitComponents` | `phil_library_kit()` et `phil_library_timezone()` |
| `Infrastructure\DependencyInjection\LibraryPass`, `KitSubdirectoryPass` | Ce qui dépend de l'application, et le sous-répertoire du kit |
| `Infrastructure\EasyAdmin\LibraryAdmin` | Les liens d'une interface d'administration EasyAdmin |
