# Symfony Mime

`symfony/mime` builds and writes messages; it does not read them. Its `Email`, `Message` and part classes are made
from values and serialized to octets, and nothing in the component turns raw octets back into those classes.

Its header classes encode as well: `UnstructuredHeader`, `ParameterizedHeader` and `DateHeader` hold a decoded value
and write it with encoded words, folding and RFC 2231 sections. Reading needs the reverse — unfolding, decoding,
joining sections — and keeping fields that no class accepts. The classes that carry addresses, `MailboxHeader`,
`MailboxListHeader`, `IdentificationHeader` and `PathHeader`, build an `Address`, which needs `egulias/email-validator`
and throws on an address it finds invalid: a hostile message is full of them, and a reader must keep them as written.

The reader therefore keeps its own small types — `Headers`, `HeaderValue`, `Mailbox`, `Part` — and adds no dependency.
An application that writes a reply or forwards a message with Symfony Mailer takes the values it needs from them:

```php
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

$from = $message->headers->mailboxes('From')[0] ?? null;

$reply = new Email()
    ->to(new Address($from->address, $from->name ?? ''))   // throws on an invalid address: catch it
    ->subject('Re: '.$message->headers->decoded('Subject'))
    ->text('…');
```
