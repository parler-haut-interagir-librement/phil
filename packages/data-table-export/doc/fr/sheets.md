<!-- phil-dev source-sha256=9beed1f4e2d60a25998ecb24e516010f73ed42d04ec09e523da1440f739f7089 -->
# Écrire une feuille

## La feuille

`Sheet` contient ce qui est écrit : un titre, les en-têtes des colonnes et les lignes.

```php
new Sheet(
    title: 'Books',                      // the worksheet's name in XLSX; CSV has none
    headings: ['Title', 'Pages', 'Read'], // the first row, none when empty
    rows: $rows,                         // any iterable of lists of cells, read once and in order
);
```

Les lignes sont lues une seule fois, à mesure qu'elles sont écrites. Un générateur qui va chercher ses lignes page par
page ne garde qu'une page en mémoire, et l'écrivain une ligne (CSV) ou un morceau de 64 Kio (XLSX).

## Les cellules

| Une cellule contient | Le CSV écrit | Le XLSX écrit |
|---|---|---|
| `null` ou `''` | un champ vide | aucune cellule |
| `true`, `false` | `1`, `0` | un booléen |
| un `int` ou un `float` fini | le nombre, avec un point | un nombre |
| `INF`, `-INF`, `NAN` | `INF`, `-INF`, `NAN` | la même chose, en texte |
| une `string` | le texte, gardé contre les formules | une chaîne en ligne |
| une `DateTimeInterface` | `2026-11-12`, ou `2026-11-12 14:30:00` quand elle a une heure | un numéro de série de date avec un format de date |
| un `BackedEnum` | sa valeur | sa valeur, en texte ou en nombre |
| un `Stringable` | sa chaîne | sa chaîne, en texte |

Toute autre valeur — un tableau, un objet sans `__toString()` — interrompt l'écriture par `ExportRefused`. Une date est
écrite à son heure d'horloge, dans son propre fuseau : convertissez-la avant quand le fichier est lu dans un autre.

## Les formats

`Format` nomme les formats : `Format::Csv` et `Format::Xlsx`. `Format::named('xlsx')` en lit un dans une requête, en
refusant tout autre nom ; `mediaType()` donne le `Content-Type` du fichier, et `fileName('books')` un nom sûr pour
`Content-Disposition`, `books.xlsx`.

`ExportWriters` contient un écrivain par format. `ExportWriters::defaults()` donne le CSV et le XLSX avec leurs valeurs
par défaut ; construisez-le avec vos propres écrivains pour les changer.

```php
$writers = new ExportWriters([new CsvWriter(separator: ';'), new XlsxWriter(rowLimit: 100_000)]);
$writers->formats();              // [Format::Csv, Format::Xlsx]
$writers->has(Format::Xlsx);      // true
$writers->write(Format::Csv, $sheet, $stream);
```

## Un téléchargement depuis un contrôleur de Symfony

```php
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

$format = Format::named($request->query->getString('format', 'csv'));
$response = new StreamedResponse(static function () use ($writers, $format, $sheet): void {
    $writers->write($format, $sheet, fopen('php://output', 'w'));
});
$response->headers->set('Content-Type', $format->mediaType());
$response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $format->fileName('books')));

return $response;
```

Une limite interrompt l'écriture par `ExportRefused` une fois la réponse commencée : vérifiez ce qui peut l'être — le
nombre de lignes — avant de diffuser, ou écrivez d'abord dans `php://temp` quand le fichier doit être complet ou
n'exister pas.
