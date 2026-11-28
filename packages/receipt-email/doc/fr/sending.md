<!-- phil-dev source-sha256=ba85d4564b49ba95dcd0bac79f62e0264575582cd6237f619ab4b83837b7491c -->
# Envoi

## Le courriel

`ReceiptEmails` construit le courriel d'un reçu à partir de son numéro :

1. il relit le reçu — `PaymentReceiptSource::find()`, `DonationReceiptRepository::find()` — et son destinataire —
   `RecipientDirectory` —, et lève `ReceiptNotFound` quand l'un des deux manque ;
2. il les transforme en `ReceiptMessage` et fait produire le rendu par le `ReceiptRenderer` ;
3. `TransactionalCheck` vérifie le résultat, et lève `NotTransactional` quand ce n'est pas un message transactionnel ;
4. il retourne un `Email` de Symfony Mime : l'expéditeur donné à son constructeur, le destinataire, le sujet, les
   parties en texte brut et en HTML, chaque image intégrée en ligne sous son nom `cid:`, l'en-tête
   `Auto-Submitted: auto-generated` pour qu'un répondeur automatique n'y réponde pas, et aucun `List-Unsubscribe`, un
   reçu n'étant pas un envoi en masse ;
5. quand l'appelant le demande, il joint le PDF du `PaymentReceiptPdf` ou du `DonationReceiptPdf`, et lève
   `PdfUnavailable` quand aucun n'est donné.

```php
$email = $emails->payment('R-000042');                       // the email alone
$email = $emails->payment('R-000042', attachPdf: true);      // with receipt-R-000042.pdf
$email = $emails->donation('D-2026-000017', attachPdf: true);
```

## Les vérifications transactionnelles

`TransactionalCheck::check()` lit le HTML avec l'analyseur HTML5 de PHP, sur 2 Mio au plus, et refuse un reçu produit
qui :

- a une partie en texte brut vide ;
- contient un `script`, `iframe`, `frame`, `object`, `embed`, `link`, `audio`, `video`, `source`, `track`, `picture`,
  un bouton image, un `meta` de rafraîchissement, ou un `srcset` ;
- montre une image, un `background` ou un `url()` de CSS qui n'est pas en `cid:` — un pixel espion, un logo distant, une
  image `data:` —, ou importe une feuille de style ;
- mène vers une adresse dont la requête porte un paramètre de campagne ou de pistage des clics : `utm_*`, `gclid`,
  `gbraid`, `wbraid`, `dclid`, `fbclid`, `msclkid`, `twclid`, `ttclid`, `li_fat_id`, `mc_cid`, `mc_eid`, `_hsenc`,
  `_hsmi`, `__hssc`, `__hstc`, `__hsfp`, `hsctatracking`, `mkt_tok`, `vero_id`, `vero_conv`, `oly_anon_id`,
  `oly_enc_id`, `_kx`, `ml_subscriber`, `ml_subscriber_hash`, `ck_subscriber_id`, `trk`, `trkid`, `ref_id`, `s_cid`,
  `icid`, `wickedid`, `yclid`, `_openstat`, `igshid` ou `srsltid`, en toute casse, encodé en pourcentage ou non, après
  `&` ou `;`. Ce qui suit `#` n'est jamais envoyé et n'est pas lu.

Un lien vers la commande, la page d'aide ou la boutique reste : la vérification refuse le pistage, pas les liens.

## Messenger

`SendPaymentReceipt` porte le numéro d'un reçu et `SendDonationReceipt` un numéro de série, chacun avec l'indication de
joindre ou non le PDF, et rien d'autre : ni adresse, ni nom, ni montant. Chacun refuse tout sauf de 1 à 64 caractères
ASCII visibles — 32 pour un numéro de série —, afin qu'un texte personnel ne soit pas porté par erreur. Acheminez-les
vers un transport asynchrone :

```php
$bus->dispatch(new SendPaymentReceipt($receipt->number));
$bus->dispatch(new SendDonationReceipt($donationReceipt->serialNumber, attachPdf: true));
```

Dans le *worker*, `SendPaymentReceiptHandler` et `SendDonationReceiptHandler` construisent le courriel avec
`ReceiptEmails` et l'envoient par un `TransportInterface` de Symfony Mailer — `mailer.transports` dans une application
Symfony —, jamais par `MailerInterface` : quand `SendEmailMessage` est acheminé de façon asynchrone, `MailerInterface`
remettrait le courriel, avec l'adresse du destinataire et le reçu, sur un transport de Messenger. Un reçu introuvable
quand le *worker* s'exécute lève `ReceiptNotFound`, que Messenger retente puis envoie à son transport des échecs.
