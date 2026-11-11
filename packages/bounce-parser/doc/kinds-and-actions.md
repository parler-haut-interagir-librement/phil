# Kinds and actions

## Kinds

| `BounceKind` | Means |
|---|---|
| `Hard` | A permanent failure against the address: the mailbox does not exist, or will never accept the message |
| `Soft` | A failure that may pass: a delay, a full mailbox, a message too long, a refusal by a filter or a policy |
| `Complaint` | The recipient, or the mailbox provider for them, reported the message as unwanted |
| `Feedback` | An abuse report that is no complaint: `not-spam`, `auth-failure` |
| `AutoReply` | An automatic reply of the recipient |
| `Delivered` | A notification of success: delivered, relayed, expanded |
| `Unknown` | Nothing the parser or the rules recognise |

A recipient of a delivery status notification is:

- `Soft` when its action is `delayed`;
- `Delivered` when its action is `delivered`, `relayed` or `expanded`, or when it has no action and a `2.x.x` status;
- otherwise, a failure: `Hard` for a `5.x.x` status, unless the status speaks of the message rather than the mailbox
  — `x.2.2` mailbox full, `x.2.3` and `x.3.4` message too long, `x.7.*` refused by policy —, which is `Soft`; `Soft`
  for a `4.x.x` status; without a status, `Soft` for an SMTP code below 500 and `Hard` otherwise, as the action
  `failed` says no attempt follows.

A refusal by policy is soft because it says nothing against the mailbox: the same address takes the next message once
the sending server is off a block list. A server that writes `5.7.1` for a mailbox that does not exist is corrected
by a rule of scope `Always` on its diagnostic.

## Actions

| `BounceAction` | The application |
|---|---|
| `Ignore` | Does nothing |
| `CountSoft` | Counts a soft bounce against the address |
| `CountHard` | Counts a hard bounce against the address |
| `Suppress` | Stops sending to the address now, whatever the counters say |
| `Unsubscribe` | Unsubscribes the address from what it was sent |
| `Review` | Keeps the message for a person |

`BounceAction::for(BounceKind)` gives the parser's action: `CountHard`, `CountSoft`, `Ignore` for automatic replies and
successes, `Unsubscribe` for complaints, `Review` for feedback and unknown messages. Two exceptions: a complaint no
authenticator vouches for goes to `Review`, and a `not-spam` report is `Ignore`. A rule sets its own action.

The library counts nothing: thresholds of consecutive bounces, the reset of a counter by a successful delivery, and the
suppression list are the application's.
