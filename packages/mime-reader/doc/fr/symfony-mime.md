<!-- phil-dev source-sha256=bff107ccb10830d6fc2144f1f64808d2409ae836dadaa78957ed5d0348f38081 -->
# Symfony Mime

`symfony/mime` construit et écrit des messages ; il ne les lit pas. Ses classes `Email`, `Message` et de parties sont
faites à partir de valeurs et sérialisées en octets, et rien dans le composant ne retransforme des octets bruts en ces
classes.

Ses classes d'en-tête encodent aussi : `UnstructuredHeader`, `ParameterizedHeader` et `DateHeader` contiennent une
valeur décodée et l'écrivent avec des mots encodés, des replis et des sections RFC 2231. La lecture demande l'inverse
— déplier, décoder, joindre les sections — et garder les champs qu'aucune classe n'accepte. Les classes qui portent
des adresses, `MailboxHeader`, `MailboxListHeader`, `IdentificationHeader` et `PathHeader`, construisent une `Address`,
qui exige `egulias/email-validator` et lance une exception sur une adresse qu'elle juge invalide : un message hostile
en est plein, et un lecteur doit les garder telles qu'écrites.

Le lecteur garde donc ses propres petits types — `Headers`, `HeaderValue`, `Mailbox`, `Part` — et n'ajoute aucune
dépendance. Une application qui écrit une réponse ou transfère un message avec Symfony Mailer y prend les valeurs
dont elle a besoin :

```php
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

$from = $message->headers->mailboxes('From')[0] ?? null;

$reply = new Email()
    ->to(new Address($from->address, $from->name ?? ''))   // throws on an invalid address: catch it
    ->subject('Re: '.$message->headers->decoded('Subject'))
    ->text('…');
```
