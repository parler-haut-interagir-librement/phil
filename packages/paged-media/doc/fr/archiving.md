<!-- phil-dev source-sha256=86c8d16e65239311233102d5de12d09f8dd8c920ba39ba0c7bd0cd347eab3aed -->
# Archivage

```php
use Phil\PagedMedia\Pdf\Conformance;

$pdf = new Document(PageSize::letter(), new Metadata(title: 'Receipt R-001', author: 'The shop'), conformance: Conformance::PdfA2u);
```

Un document qui reçoit `Conformance::PdfA2u` est écrit en PDF/A-2u (ISO 19005-2, niveau U) : il a le même aspect dans
tous les lecteurs aussi longtemps que le fichier est conservé, et son texte peut être extrait en Unicode. C'est le
format des documents qu'une loi demande de conserver pendant des années, comme les reçus et les factures.

## Ce que le document ajoute

| Ajout | Raison |
|---|---|
| Un flux de métadonnées XMP, non compressé, avec la conformité et les mêmes titre, auteur, sujet, mots-clés, créateur, producteur et date de création que le dictionnaire d'information | PDF/A s'identifie en XMP, et exige que les deux concordent |
| Une intention de sortie sRGB, avec un profil ICC écrit à partir de la colorimétrie de l'IEC 61966-2-1 | Elle définit les couleurs données en RGB et en gris |
| Un identifiant de fichier dans la remorque, l'empreinte des objets du fichier | PDF/A en exige un ; une empreinte garde la sortie déterministe |

Les polices TrueType intégrées portent déjà leur correspondance vers Unicode, qu'exige le niveau U.

## Ce que le document refuse

Un document PDF/A refuse, dès qu'on le dessine, ce que la norme interdit :

| Refusé | Raison | À la place |
|---|---|---|
| `StandardFont::Helvetica` et `HelveticaBold` | PDF/A intègre chaque police, et les polices standard ne sont pas intégrées | Une `TrueTypeFont` |
| Un caractère absent de la police TrueType | PDF/A interdit d'imprimer le glyphe manquant | Une police qui contient ce caractère |
| Un JPEG CMYK | L'intention de sortie est sRGB | L'image en RGB ou en gris |

Chaque refus lève `InvalidDocument`, en nommant ce qui ne va pas.

## Vérifier un document

Les tests de la bibliothèque vérifient la structure. [veraPDF](https://verapdf.org), le validateur de référence,
vérifie la conformité :

```sh
verapdf --flavour 2u --format text receipt.pdf
```
