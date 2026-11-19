# Changelog

All notable changes to `phil/library-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `Title`, `Copy` and `Loan`, mapped to `phil_library_title`, `phil_library_copy` and `phil_library_loan`, moments in
  `datetime_utc_immutable` columns, each with a version; a copy's barcode unique, its condition (`CopyCondition`), its
  note and the `phil/stockroom` item it is; a loan's member a stable identifier, an e-mail address refused
  (`Member`), its states (`LoanStatus`), renewals, last reminder and notices; `InvalidLibraryData`.
- `Record`, the description of a title (`TitleKind`): name, ISBN checked by its check digit and kept as ISBN-13,
  creators, publisher, year, players, length of a play, youngest age, language and summary, each bounded.
- The `TitleRepository`, `CopyRepository` and `LoanRepository` ports and their Doctrine adapters: the ORM for one
  entity and the short lists, DBAL for the counts and the bulk changes of the history.
- `Catalogue`: titles added and described, copies added with unique barcodes, assessed and linked to a stockroom item.
- `Loans` on `phil/reservation`, each copy a resource of one unit: `lend()`, due at the end of its last day on the
  library's clock, `reserve()`, `checkOut()`, `renew()` from the due date, `giveBack()` with the copy's condition,
  `cancel()`, `lose()`, `isFree()`, `holding()`, `ofMember()` and `forgetHistory()`; the member's limit of open loans
  and the block of a copy overdue; each loan claiming its copy, so that two loans of one copy made at once are not both
  kept; `LoanRefused` with a stable reason and its parameters, `NotFound`; the events `CopyLent` and `CopyReturned`,
  with identifiers only; `LoanPolicy`.
- The `StockLink` port, `StockroomLink`, which moves a linked copy's `phil/stockroom` item out to the loan, back home
  or lost, the loan as its holder, and `NoStockLink`; with `phil/stockroom-bundle`, its stockroom taken by default and
  each movement run in its transactions, its item locked.
- The `LoanTransaction` port and `DbalLoanTransaction`: a loan that moves stock — lent, checked out, returned, lost —
  kept with its movement in one transaction when the stockroom's tables are on the library's connection.
- `TitleSearch` and `DbalTitleSearch`: words in the name or the creators, `%` and `_` taken literally, an ISBN in
  either form, a kind, a group of players, a length, an age, a language and the titles on the shelf now, paged, with
  the copies and those on the shelf counted (`SearchCriteria`, `SearchResult`, `TitleRow`).
- `Reminders` with the `ReminderSender` port: reminders before the due date, notices of a loan overdue every so many
  days up to a number, each sent once, no fine counted; `MessengerReminderSender` and the `SendLoanReminder` message,
  identifiers only.
- `phil:library:send-reminders` and `phil:library:forget-history`.
- `LoanHistoryPrivacy`, the `library` module of `phil/privacy-bundle`: a member's loans exported, and forgotten on
  erasure.
- The pages, drawn by the `Alert`, `Badge`, `Button`, `Card` and `Table` recipes of the PHIL UX kit: the catalogue
  searched by a form sent by GET and paged by links, without JavaScript; a title and where each copy is; the member's
  loans through the application's `MemberResolver`; the desk; the administration of titles and copies; the loans
  overdue; the staff pages behind a role, refused without security; `SearchType`, `LendType`, `ReturnType`,
  `TitleType` and `CopyType`; the texts in the `phil_library` domain and the messages of the constraints in
  `validators`, in English and French; `phil_library_kit()` and `phil_library_timezone()`.
- `LibraryAdmin`, the links of an EasyAdmin back office to the pages.
- The configuration `phil_library`: time zone, loans, reminders, history, batch, page size, member resolver,
  stockroom, roles, layout and kit subdirectory.
- The Flex recipe in `recipe/`, ready for the recipes repository.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
