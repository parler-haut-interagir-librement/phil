# Extension points

The bundle is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Name the member of a request | Implement `Application\Contract\MemberResolver` and name its service in `member_resolver`; there is no default, and none reads an e-mail address |
| Send the reminders another way | Implement `Application\Contract\ReminderSender` and alias it; with Messenger, handle `SendLoanReminder` instead |
| Move the stock of linked copies | Install `phil/stockroom-bundle`, set `stockroom` to the application's `Phil\Stockroom\Stockroom`, or implement `Application\Contract\StockLink` and alias it |
| Keep a loan and its stock movement together | Install `phil/stockroom-bundle` on the library's connection, or implement `Application\Contract\LoanTransaction` and alias it |
| Search the catalogue another way | Implement `Application\Contract\TitleSearch` and alias it |
| Keep the titles, copies or loans elsewhere | Implement the ports of `Domain\Contract` and alias them |
| React to a loan | Listen to `Domain\Event\CopyLent` and `Domain\Event\CopyReturned`, which carry identifiers only |
| Change a page | Override its template under `templates/bundles/PhilLibraryBundle/`, or point `pages.layout` to the application's layout |
| Reach the pages from a back office | `Infrastructure\EasyAdmin\LibraryAdmin::menuItems()` |
| Add the history to the privacy requests | Install `phil/privacy-bundle`: `LoanHistoryPrivacy` is tagged as its exporter and eraser |

## The ports of the domain

| Port | Adapter |
|---|---|
| `TitleRepository` | `DoctrineTitleRepository`, the ORM |
| `CopyRepository` | `DoctrineCopyRepository`, the ORM |
| `LoanRepository` | `DoctrineLoanRepository`, the ORM for a loan and the short lists, DBAL for the counts and the bulk changes |

`LoanRepository::save()` keeps a loan and the copy it claims in one write; an adapter of the application's keeps that,
so that two loans of one copy made at once are not both kept.
