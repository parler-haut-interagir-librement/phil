<!-- phil-dev source-sha256=9b3e2fb9969668b4da420da3cb681ca22df767751fb9a29076318b6b6c9b26a8 -->
# Impression

Avec `phil/paged-media-bundle` installé, le paquet garde les impressions de `PagedMedia` et de `PagedMediaQueue`, tout
de suite ou avant leur mise en file : ce qu'il refuse parvient à l'appelant, et rien n'est rendu, envoyé ni conservé.

## La garde d'exportation avant une impression

`GuardPrintedWork` exécute la [garde d'exportation](../compliance.md#the-export-guard) avant chaque impression qui
nomme son œuvre par un `PrintSubject` : `ExportBlocked` arrête une œuvre sans déclaration, ou dont la déclaration omet
ce qu'une licence demande.

```php
$pagedMedia->printHtml($page, 'book.pdf', subject: new PrintSubject('book', (string) $book->getId()));
```

Le type et l'identifiant sont ceux du `SubjectReference` de la déclaration.

## Les documents sans licence

Une facture, un billet ou un reçu n'est pas une œuvre : son type de sujet va dans `unlicensed_subjects`, et ses
impressions se font sans vérification.

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    printing:
        unlicensed_subjects: [invoice, ticket]
```

```php
$pagedMedia->render($invoice, 'invoice.pdf', subject: new PrintSubject('invoice', $number));
```

## L'impression stricte

Par défaut, une impression qui ne nomme aucune œuvre n'est pas vérifiée : le paquet ne peut pas savoir de quoi elle est
faite. Une application qui ne publie que des œuvres, où chaque impression doit porter sa licence, active l'impression
stricte :

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    printing:
        strict: true
        unlicensed_subjects: [invoice]
```

`RequirePrintSubject` écoute alors `UnnamedPrintStarting`, que `phil/paged-media-bundle` répartit avant une impression
sans `PrintSubject`, et lance `PrintSubjectRequired`. Un document sans licence s'imprime encore s'il nomme un type de
sujet de `unlicensed_subjects` : être déclaré sans licence est la seule façon d'échapper à la vérification.

`PrintSubjectRequired` est une `LicensingException` et une `TranslatableInterface` : son message pour le lecteur est
`print.subject_required` du domaine `phil_licensing`, en anglais et en français.

```php
try {
    $pagedMedia->render($document, 'book.pdf');
} catch (PrintSubjectRequired $refused) {
    $this->addFlash('error', $refused->trans($translator));
}
```

Le worker qui fait une impression mise en file n'annonce rien : l'impression a été vérifiée dans la requête qui l'a
mise en file.
