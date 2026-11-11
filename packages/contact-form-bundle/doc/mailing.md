# Mailing

`ContactMailer` sends a `TemplatedEmail`:

| Part | Is | Why |
|---|---|---|
| `From` | The `sender` | A visitor's address in `From` fails the domain's SPF and DMARC, and the message lands in spam |
| `To` | The `Recipients` of the message | |
| `Reply-To` | The visitor, with their name | Answering answers them |
| `Subject` | The `subject_prefix`, then the subject or the visitor's name | |
| `X-Auto-Response-Suppress` | `OOF, AutoReply` | Out-of-office replies are not sent to the form |

Line breaks in the name and the subject are replaced by spaces: no header is added by what a robot posts. The body
is `@PhilContactForm/email/message.html.twig` and `message.txt.twig`, given `contact`, in the language of the
request.

Nothing is sent to the visitor: a copy would let anyone send a text of their choice to any address through the site.

## Sending through Messenger

When the application routes `SendEmailMessage` to an asynchronous Messenger transport, `ContactMailer::send()` only
queues the email. The page then redirects as if the message left: a transport that refuses it fails in the worker,
which retries it and logs the failure, and the `503` answer with the form still filled never happens.

The queued email holds the visitor's name, address and message. They stay in the Messenger transport until the worker
sends them, and in its failure transport, if one is configured, until someone retries or removes them. The bundle
keeps nothing itself, but such an application does keep personal information there: it needs a retention rule for
its failure transport — `messenger:failed:show` to look, `messenger:failed:remove` to purge — and should say so in
its privacy policy.
