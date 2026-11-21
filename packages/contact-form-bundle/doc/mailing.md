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

`ContactMailer` sends through Symfony Mailer's transports (`mailer.transports`), never through `MailerInterface`: the
email is rendered and sent in the request, and a refusal of the transport is known at once, which the page answers
with `503` and the form still filled. An application that routes `SendEmailMessage` to an asynchronous Messenger
transport still queues nothing of a message: through `MailerInterface`, the queued message would hold the unrendered
email with the visitor's name, address and message in its context, kept by the transport and by its failure transport
until someone retries or removes it.

What stays the application's: the mailer's own configuration — DSN, `X-Transport`, `envelope`, `headers` —, which the
transports apply as they would to any email, and the emails it sends itself. A controller of the application that
calls `ContactMailer::send()` catches `TransportExceptionInterface`, as the bundle's page does.
