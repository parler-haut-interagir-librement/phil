<!-- phil-dev source-sha256=a33ecd19c31f252c67b353d78d649eaf4bd561e9ea312a2996ab647db9faa887 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `WeasyPrintEngine` | Le `PrintEngine` qui appelle la commande `weasyprint` : `pdf()`, `version()`, `assertSupported()`, `MINIMUM_VERSION` |
| `LocalAssets` | Le répertoire où un document prend ses images, ses polices et ses profils ICC : `inline()` les écrit dans le document |
| `PdfOptions` | La norme, l'intention de sortie, les balises, les formulaires et les images du PDF : `arguments()` donne les arguments de la commande |
| `PdfVariant` | Les normes que WeasyPrint écrit, PDF/A, PDF/UA et PDF/X : `isPdfX()` |
| `Exception\WeasyPrintFailed` | Une impression qui a échoué, avec `exitCode` et `errorOutput` |
