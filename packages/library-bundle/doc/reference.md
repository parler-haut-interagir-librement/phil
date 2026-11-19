# Reference

Every type of the bundle, with its role.

| Type | Role |
|---|---|
| `PhilLibraryBundle` | Wires the services, maps the entities, configures `phil_library` |
| `Domain\Model\Title` | A title of the catalogue and its record |
| `Domain\Model\Copy` | One object of a title, lent; its barcode, condition, note and stockroom item |
| `Domain\Model\Loan` | A copy lent to a member for a span |
| `Domain\ValueObject\Record` | What a title is described by; its ISBN kept as ISBN-13 |
| `Domain\ValueObject\Member` | The shape of a member's stable identifier |
| `Domain\Status\TitleKind` | Game, book, other |
| `Domain\Status\CopyCondition` | Good, worn, damaged, incomplete, withdrawn |
| `Domain\Status\LoanStatus` | Reserved, active, returned, cancelled, lost |
| `Domain\Contract\TitleRepository`, `CopyRepository`, `LoanRepository` | The ports of the titles, copies and loans |
| `Domain\Event\CopyLent`, `CopyReturned` | The events of a copy handed over and back, with identifiers only |
| `Domain\Exception\InvalidLibraryData` | A value or a change refused |
| `Application\Catalogue` | Titles, records, copies, conditions, stockroom links |
| `Application\Loans` | Lends, reserves, checks out, renews, takes back, cancels, loses; forgets the history |
| `Application\LoanPolicy` | The rules of the loans, and a copy as `phil/reservation` sees it |
| `Application\Reminders` | Sends the reminders and notices due |
| `Application\Search\SearchCriteria`, `SearchResult`, `TitleRow` | A search of the catalogue and a page of it |
| `Application\Contract\TitleSearch` | The port of the search |
| `Application\Contract\StockLink` | The port of the stock moves |
| `Application\Contract\ReminderSender` | The port that sends a reminder |
| `Application\Contract\MemberResolver` | The port that names the member of a request |
| `Application\Message\SendLoanReminder` | The Messenger message of a reminder |
| `Application\Exception\LoanRefused` | A refusal, with its reason and parameters |
| `Application\Exception\NotFound` | A title, a copy or a loan missing, or a copy not out |
| `Infrastructure\Doctrine\DoctrineTitleRepository`, `DoctrineCopyRepository`, `DoctrineLoanRepository` | The repositories with Doctrine |
| `Infrastructure\Doctrine\DbalTitleSearch` | The search with DBAL |
| `Application\Contract\LoanTransaction` | The port that runs a loan and its stock movement in one transaction |
| `Infrastructure\Doctrine\DbalLoanTransaction` | That transaction on the library's DBAL connection, wired when `phil/stockroom-bundle` shares it |
| `Infrastructure\Stockroom\StockroomLink` | The stock moves through `phil/stockroom`, in the transactions of `phil/stockroom-bundle` when it is installed |
| `Infrastructure\Stockroom\NoStockLink` | No stockroom: nothing moves |
| `Infrastructure\Messenger\MessengerReminderSender` | Sends each reminder as a message |
| `Infrastructure\Privacy\LoanHistoryPrivacy` | The history of a member's loans in the privacy requests |
| `Infrastructure\Console\SendRemindersCommand` | `phil:library:send-reminders` |
| `Infrastructure\Console\ForgetHistoryCommand` | `phil:library:forget-history` |
| `Infrastructure\Form\SearchType`, `LendType`, `ReturnType`, `TitleType`, `CopyType`, `TitleData` | The forms of the pages |
| `Infrastructure\Http\CatalogueController`, `DeskController`, `AdminController`, `MemberLoansController`, `Pages` | The pages |
| `Infrastructure\Twig\LibraryExtension`, `KitComponents` | `phil_library_kit()` and `phil_library_timezone()` |
| `Infrastructure\DependencyInjection\LibraryPass`, `KitSubdirectoryPass` | What depends on the application, and the kit's subdirectory |
| `Infrastructure\EasyAdmin\LibraryAdmin` | The links of an EasyAdmin back office |
