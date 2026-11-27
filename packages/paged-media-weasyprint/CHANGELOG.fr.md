<!-- phil-dev source-sha256=a13dc02abefa177f647222a35ecbe0b35fd918e9abad46559767a28e070fa24d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/paged-media-weasyprint` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `WeasyPrintEngine` : le `PrintEngine` de `phil/paged-media-bundle` sur la commande `weasyprint`, le document sur son
  entrée standard et le PDF sur sa sortie standard, seules les adresses `data:` récupérées, si bien qu'aucune image
  distante n'est lue, un délai d'attente configurable ; la version de la commande lue et vérifiée d'après WeasyPrint 70
  ; enregistré sous le port par une ligne de `services.yaml`.
- `PdfOptions` et `PdfVariant` : PDF/A, PDF/UA et PDF/X, l'intention de sortie (`srgb`, `device-cmyk` ou le nom à tirets
  d'un `@color-profile`, exigé pour PDF/X), les balises, les champs de formulaire, la résolution et la qualité JPEG des
  images et leur optimisation, chacun un argument vérifié de la commande.
- `LocalAssets` : les images, les polices et les profils ICC d'un dossier écrits dans un document comme adresses
  `data:`, les références résolues dans le dossier, les extensions listées, une limite par fichier et par document.
- `WeasyPrintFailed`, avec le code de sortie et la sortie d'erreur de la commande.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
