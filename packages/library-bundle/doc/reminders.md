# Reminders and privacy

## Reminders and notices

`Reminders::send($batch)` reads the active loans due soon or overdue and sends:

- a reminder before the due date, at each offset of `reminders.before_due` — a reminder whose moment passed unsent is
  overtaken by the next one;
- a notice once the loan is overdue, then one every `reminders.overdue_every_days`, up to `reminders.overdue_max`.

Each goes once: the loan records the offset of the last reminder and the number and moment of the last notice. A
renewal starts them over. No fine is counted: a notice says the copy is awaited.

They go through the `ReminderSender` port; with Messenger, its adapter dispatches a `SendLoanReminder`:

| Property | Value |
|---|---|
| `loanId` | The loan |
| `kind` | `due` before the due date, `overdue` after it |
| `step` | The offset in minutes, or the number of the notice from 1 |

The message carries identifiers only: the application's handler reads the loan and its member, and writes to them as
it does, on the transport it routes the message to.

```php
#[AsMessageHandler]
final readonly class LoanReminderHandler
{
    public function __invoke(SendLoanReminder $message): void
    {
        $loan = $this->loans->get($message->loanId);
        // … the member's address from the application's accounts, the message in their language
    }
}
```

Without Messenger nor a `ReminderSender` of the application's, there is neither the reminders' service nor its command.

## The commands

| Command | What it does | When |
|---|---|---|
| `phil:library:send-reminders` | Sends the reminders and notices due, for at most `batch` loans | Every hour or so |
| `phil:library:forget-history` | Forgets the member of the loans closed more than `history_days` ago | Every day |

## The history in the privacy requests

With `phil/privacy-bundle`, `LoanHistoryPrivacy` is the `library` module of an access or erasure request, the data
subject being the member's identifier:

- the export lists each of the member's loans — the title, the copy's barcode, its state, its span, when it was handed
  over and closed, its renewals —, at most 1000;
- the erasure forgets the member on all their loans, which stay as counts of the copies' use
  (`phil_library_loan.member` redacted). An active loan stays active, nobody's.

Running either again finds nothing more and reports the same. A subject that is not an identifier — an e-mail address
— holds nothing here.

## Forgetting the history

Many libraries keep no history of what a member borrowed. With `history_days`, `phil:library:forget-history` forgets
the member of every loan returned, cancelled or lost more than so many days ago; with `0`, as soon as it closes. The
loans stay, nobody's. Without the option, the history is kept until the member's data is erased.
