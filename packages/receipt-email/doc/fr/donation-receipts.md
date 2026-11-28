<!-- phil-dev source-sha256=ae47e9a3a19944c6263e4d28a0a5eb00bcf56919c5f8cb3d895059183c8140d7 -->
# Reçus de don

Un reçu officiel de don est un document à part, jamais un reçu de paiement : un `Donation` — le don tel que le reçu
l'énonce —, un numéro de série pris dans la série des reçus de don, et la date d'émission.

## Ce qu'un reçu officiel énonce

L'Agence du revenu du Canada énumère ce qu'un reçu officiel de don d'un organisme de bienfaisance enregistré doit
contenir, sur sa page
[What information must be on an official donation receipt from a registered charity?](https://www.canada.ca/en/revenue-agency/services/charities-giving/charities/operating-a-registered-charity/issuing-receipts/what-information-must-on-official-donation-receipt-a-registered-charity.html)
(page datée du 2018-11-02, lue le 2026-11-21). Chaque élément de cette liste et l'endroit où le paquet le garde :

| Le reçu énonce | Gardé dans |
|---|---|
| Qu'il est un reçu officiel aux fins de l'impôt sur le revenu | Les gabarits et le PDF, comme titre |
| Le nom et l'adresse de l'organisme, tels qu'inscrits à l'Agence | `Donation::$charity` |
| Un numéro de série unique | `DonationReceipt::$serialNumber`, de `DonationReceiptNumbering` |
| Le numéro d'enregistrement que l'Agence a délivré | `Donation::$registrationNumber` |
| Le lieu d'émission du reçu : la ville, le village ou la municipalité | `Donation::$issuedIn` |
| La date, ou l'année, où le don a été reçu | `Donation::$received` : un `DateTimeImmutable`, ou l'année en entier |
| La date d'émission du reçu | `DonationReceipt::$issuedAt` |
| Le nom complet du donateur, avec l'initiale de son deuxième prénom, et son adresse | `Donation::$donor` |
| Le montant du don | `Donation::$amount` |
| Le montant et la description de tout avantage que le donateur a reçu | `Donation::$advantage`, `Donation::$advantageDescription` |
| Le montant admissible du don | `Donation::eligibleAmount()` |
| La signature d'une personne que l'organisme autorise à accuser réception des dons | `Donation::$signatory` ; le PDF imprime l'image de la signature au-dessus du nom quand on lui en donne une |
| Le nom et l'adresse du site Web de l'Agence | `DonationReceipt::authority()`, `DonationReceipt::AUTHORITY_WEBSITE` : `canada.ca/charities-giving` |

Un don de biens énonce aussi la date où il a été reçu, une brève description du bien, et le nom et l'adresse de
l'évaluateur quand le bien a été évalué : `GiftKind::NonCash`, `Donation::$giftDescription`, `Donation::$appraiser`.
Son montant est sa juste valeur marchande au moment du don. La même page demande que le reçu soit lisible et difficile
à modifier : le PDF est la copie à conserver.

## L'avantage et le montant admissible

Le montant admissible est le montant du don moins l'avantage. `Donation::of()` refuse :

- un montant de zéro ou moins ;
- un avantage inférieur à zéro, ou aussi grand que le don ou plus grand : le montant admissible est toujours supérieur
  à zéro ;
- un avantage dans une autre devise que le don ;
- un avantage supérieur à zéro sans sa description ;
- un don de biens sans sa description, et un évaluateur sur un don en espèces ;
- une année de réception hors de 1900 à 9999, et les textes que le reçu imprime lorsqu'ils sont vides, de plus de 500
  caractères — 64 pour le numéro d'enregistrement —, ou contiennent des sauts de ligne ou des caractères de contrôle.

Savoir si un don donne droit à un reçu — un seuil sur l'avantage, la nature du bien, le donateur — est la décision de
l'organisme, que le paquet ne prend pas.

## La série

`DonationReceiptNumbering::next()` donne le prochain numéro de série des reçus de don : une série à part, séquentielle
et sans trous, jamais partagée avec les reçus de paiement. `DonationReceipts::issue()` prend un numéro, construit le
reçu et le garde dans le `DonationReceiptRepository` ; l'application l'appelle dans la transaction qui garde le reçu,
afin qu'un numéro pris ne soit jamais perdu :

```php
use Phil\ReceiptEmail\Application\DonationReceipts;
use Phil\ReceiptEmail\Domain\Donation\GiftKind;
use Phil\ReceiptEmail\Domain\ValueObject\Donation;
use Phil\ReceiptEmail\Domain\ValueObject\Party;

$donation = Donation::of(
    Party::of('The Reading Circle', ['8 Library Lane', 'Montréal QC  H2X 2Z9']),
    '123456789 RR 0001',
    'Montréal',
    Party::of('Ada B. Lovelace', ['1 Analytical Road', 'Québec QC  G1R 4P5']),
    new DateTimeImmutable('2026-11-18'),
    GiftKind::Cash,
    Money::ofDecimal('150.00', Currency::of('CAD')),
    Money::ofDecimal('40.00', Currency::of('CAD')),
    'A ticket to the gala dinner',
    null,
    null,
    'Grace Hopper, treasurer',
);

$receipt = new DonationReceipts($numbering, $repository)->issue($donation, $clock->now());
$bus->dispatch(new SendDonationReceipt($receipt->serialNumber, attachPdf: true));
```

`Donation::of()` vérifie un don avant que le numéro soit pris : un don refusé n'en utilise jamais un.

## Le PDF

`Infrastructure\Pdf\PagedMediaDonationReceiptPdf` imprime chacune des mentions ci-dessus avec `phil/paged-media`, au
format Lettre sauf si on lui donne un autre format de page, sur plusieurs pages quand les adresses et les descriptions
l'exigent. Ses libellés sont en anglais, ou en français pour une locale française (`fr`, `fr_CA`) ; une application
remplace n'importe lequel d'entre eux par locale ou par langue :

```php
use Phil\PagedMedia\Image\Image;
use Phil\ReceiptEmail\Infrastructure\Pdf\PagedMediaDonationReceiptPdf;

$pdf = new PagedMediaDonationReceiptPdf(
    labels: ['en' => ['donor' => 'Donor (name and address)']],
    signature: Image::fromFile('/path/to/signature.png'),
);
```

Helvetica imprime les langues occidentales ; les noms dans une autre écriture exigent des polices TrueType, qu'exige
aussi un reçu archivé en PDF/A-2u (`Conformance`). `ReceiptEmails::donation($serialNumber, attachPdf: true)` le joint
sous le nom `donation-receipt-<serial number>.pdf`.
