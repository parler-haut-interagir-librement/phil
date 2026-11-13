<!-- phil-dev source-sha256=2ad185fd7286fd2a7e2c649ceef2624fa646251019870c323382470140589d0b -->
# Rendu

`PagedMedia` réunit le moteur d'écriture natif et Gotenberg derrière un seul service. Chaque méthode répond par un
`Output` : le `contents`, le `filename`, le `contentType`, et les comptes `residual` et `rolledBack` quand une étape
`remediate` a été exécutée.

## Le moteur d'écriture natif

```php
public function __construct(private PagedMedia $pagedMedia) {}

$output = $this->pagedMedia->render($document, 'ticket.pdf');                       // the native writer alone
$output = $this->pagedMedia->render($document, 'ticket.pdf', new Pipeline(
    new Remediate(Profile::PdfA3b),
    $this->pagedMedia->signature(),
));                                                                                    // then through Gotenberg
```

Le rendu d'un document est produit par le `Document` de la bibliothèque. Quand des étapes sont données, il est envoyé à
Gotenberg sous son nom de fichier et les étapes s'y appliquent.

## Gabarits

Un gabarit dessine un document du moteur d'écriture natif à partir de paramètres. Il est connu par le nom de son
attribut :

```php
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

#[AsDocumentTemplate('ticket')]
final readonly class TicketTemplate implements DocumentTemplate
{
    public function build(array $parameters): Document
    {
        $document = new Document(PageSize::a5());
        $document->addPage();
        $document->text(36, 540, 'Seat '.$parameters['seat'], 18, StandardFont::HelveticaBold);

        return $document;
    }
}

$output = $this->pagedMedia->renderTemplate('ticket', ['seat' => 'A12'], 'A12.pdf');
```

Les paramètres sont des scalaires et des tableaux, puisque le stockage des tâches les écrit en JSON. Un nom inconnu
lance `UnknownTemplate`, qui énumère les noms connus.

## Gotenberg

```php
$output = $this->pagedMedia->process(Files::of(InputFile::fromPath($pdf)), new Pipeline(new Merge(), new Optimize()));
$output = $this->pagedMedia->convert(Files::of(InputFile::fromPath($docx)), new LibreOfficeOptions(nativePdfa: Profile::PdfA2b));
$gotenberg = $this->pagedMedia->gotenberg();   // the client itself: the read routes, webhooks, saveTo()
```

Le fichier produit prend le nom que donne Gotenberg, ou celui du premier fichier. Sans `gotenberg.url`, ces méthodes
lancent `NotConfigured`. La documentation de la bibliothèque décrit les étapes, les règles d'ordre et les options de
LibreOffice.

## HTML et CSS

La mise en page du HTML et du CSS est confiée à un moteur de médias paginés choisi par l'application, un service
enregistré sous `Phil\PagedMediaBundle\Application\Contract\PrintEngine` :

```php
$output = $this->pagedMedia->printHtml(new PrintableHtml($html, [$css], 'fr', 'The book'), 'book.pdf', new Pipeline(new Merge()));
```

`PrintableHtml::document()` donne au moteur le document entier. Avec des étapes, le PDF continue vers Gotenberg. Sans
moteur, `printHtml()` lance `NotConfigured`.

`phil/paged-media-weasyprint` est un moteur prêt à l'emploi, qui repose sur la commande `weasyprint`. Le bundle
n'exige rien de la machine pour lui : une application qui le veut installe le paquet et l'enregistre sous
l'interface. Sa documentation couvre le branchement, la version de WeasyPrint et ce qu'un document peut télécharger.

## Notes en bas de page

Quand `phil/footnotes-sidenotes` est installé, `PrintedNotes` transforme un document UJO (Universal JSON Output) en
page pour le moteur :

```php
$printed = $this->printedNotes->page($document, 'fr', title: 'The book');

if ([] !== $printed->resolution->problems) {
    // a reference without a note, a note never referenced: refuse, warn or carry on
}

$output = $this->pagedMedia->printHtml($printed->page, 'book.pdf');
```

Les notes sont résolues par le service `Phil\FootnotesSidenotes\NoteResolver`, qu'une application redéfinit pour sa
numérotation. Elles sont placées en bas de page, ou comme le dit `$placement`, avec les libellés de la langue de la
page et les feuilles de style d'écran et d'impression du paquet.

`countPerPage: true` ajoute la feuille de style qui confie les appels et les marqueurs au compteur de notes du moteur,
pour les numéros que seul le moteur peut connaître. Un moteur qui suit CSS GCPM le remet à zéro à chaque page.
WeasyPrint 70 compte les notes avant de mettre les pages en page et ignore cette remise à zéro : ses numéros se
poursuivent d'une page à l'autre. Les libellés du résolveur sont alors le meilleur choix, avec une remise à zéro par
chapitre ou par marqueur de page.

## Qui peut imprimer une œuvre

Chaque méthode qui imprime prend un `PrintSubject`, le type et l'identifiant de l'œuvre. `PagedMedia` et
`PagedMediaQueue` répartissent alors d'abord `PrintStarting`. Un écouteur qui lance une exception arrête l'impression
avant que quoi que ce soit ne soit rendu, envoyé ou mis en file d'attente. `phil/licensing-bundle` l'écoute avec sa
garde d'exportation.

```php
$output = $this->pagedMedia->render($document, 'book.pdf', subject: new PrintSubject('book', '42'));
```

Une impression qui ne nomme aucune œuvre répartit plutôt `UnnamedPrintStarting`, qui ne porte rien : un écouteur qui
exige que chaque impression nomme son œuvre lance une exception, et l'impression s'arrête de la même façon.

## Signatures et horodatage

```php
$sign = $this->pagedMedia->signature();                              // what the configuration says
$sign = $this->pagedMedia->signature(reason: 'Contract', pades: true); // an argument wins over the configuration
$stamp = $this->pagedMedia->timestamp();

$pipeline = $this->pagedMedia->finish($pipeline, sign: true, timestamp: true);
```

`signature()` prend le certificat que la source configurée donne à ce moment, et l'autorité d'horodatage configurée
atteste son heure de signature. `finish()` termine une liste par la signature et un horodatage du document quand on le
demande, ou crée une liste qui les contient quand il n'y en a pas. Sans certificat ni autorité, ces méthodes lancent
`NotConfigured`.
