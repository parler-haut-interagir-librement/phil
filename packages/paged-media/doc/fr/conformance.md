<!-- phil-dev source-sha256=03e2f3699738a814c2ff749c167c0a74f79365d4106ceed397aaf373e5620fe9 -->
# Conformité

Gotenberg répare un PDF selon une norme avec l'étape `remediate`, et le vérifie avec la route `validate`. Les deux
acceptent un `Profile`.

## Profils

| Profil | Norme |
|---|---|
| `PdfA1a`, `PdfA1b` | PDF/A-1, sur PDF 1.4 |
| `PdfA2a`, `PdfA2b`, `PdfA2u` | PDF/A-2 : transparence et calques |
| `PdfA3a`, `PdfA3b`, `PdfA3u` | PDF/A-3 : fichiers intégrés de tout type |
| `PdfA4`, `PdfA4e`, `PdfA4f` | PDF/A-4, sur PDF 2.0 ; `PdfA4f` exige un fichier intégré |
| `PdfUa1`, `PdfUa2` | PDF/UA, pour l'accessibilité |
| `FacturX` | Factur-X (ZUGFeRD), une facture électronique PDF/A-3 |

La lettre dit ce que garde un PDF/A : `b` son aspect, `u` aussi son texte en Unicode, `a` aussi sa structure, par des
balises. Deux profils vont ensemble quand l'un est un profil PDF/A ou Factur-X et l'autre le profil PDF/UA de sa
version de PDF : PDF/A-1 à PDF/A-3 et Factur-X avec `PdfUa1`, PDF/A-4 avec `PdfUa2`. `Profile::checked()` refuse toute
autre liste.

## Remédier

```php
$result = $gotenberg->pipe($files, new Pipeline(
    new Remediate([Profile::PdfA2b, Profile::PdfUa1], language: 'fr-CA', figureAltText: 'Illustration'),
    new Optimize(),
));

$result->residual;     // the violations left: 0 means every document conforms to every profile
$result->rolledBack;   // the fixes undone because they lost pages, text or images
$result->conforms();   // true when the residual is 0
```

Gotenberg répond 200 même quand des violations restent : lisez le résiduel, et listez ce qui reste avec `validate()`.
`aggressive: true` permet des correctifs qui peuvent changer l'aspect des pages, avec le même retour arrière ; sur
PDF/A-1, il supprime chaque fichier intégré.

Une remédiation Factur-X intègre le XML de facture fourni, au niveau que déclare le XML, ou répare la facture que porte
déjà le PDF :

```php
new Remediate(Profile::FacturX, xml: InputFile::fromPath('factur-x.xml'), level: FacturXLevel::En16931);
```

Le XML et son niveau vont ensemble, et seulement avec `FacturX` ; un type de document, `INVOICE` par défaut, accompagne
le XML.

Une remédiation vient après les étapes qui changent le contenu : seuls `encrypt`, `optimize`, `sign`, `timestamp` et
une étape terminale peuvent la suivre, et un PDF/A ou un Factur-X ne peut pas être chiffré.

## Valider

```php
$reports = $gotenberg->validate(Files::of($pdf), [Profile::PdfA3b, Profile::PdfUa1]);

$report = $reports['invoice.pdf'];
$report->compliant;    // true only when the PDF conforms to every profile
$report->violations;   // list<Violation>
$report->fatal();      // the violations that make it non-compliant
```

Chaque `Violation` nomme la norme (`ISO_19005_2`), sa clause et son test, ce que dit la règle, l'endroit où le PDF
l'enfreint, et sa `Severity` : `Fatal`, ou `Warning`, levée seulement par les règles plus souples de Factur-X.

## Le moteur d'écriture natif

`Document` écrit lui-même du PDF/A-2u ([Archivage](archiving.md)). `remediate` atteint les autres niveaux, PDF/UA et
Factur-X, sur n'importe quel PDF, et `validate` vérifie ce qu'a écrit le moteur d'écriture natif :

```php
$gotenberg->validate(Files::of(InputFile::fromDocument('archive.pdf', $document)), Profile::PdfA2u);
```
