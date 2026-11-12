<!-- phil-dev source-sha256=b4902a4deef05b7c77af789d8297e6e203d96f6142c523e246feebc249a23bd5 -->
# CSV

`CsvWriter` écrit la RFC 4180 : UTF-8, chaque champ entre guillemets quand il contient le séparateur, un guillemet ou une
fin de ligne, un guillemet doublé, chaque ligne terminée par CRLF. Il écrit chaque ligne à mesure qu'il la lit.

```php
new CsvWriter(
    separator: ',',        // one byte, neither a quote nor an end of line: ',', ';' or "\t"
    byteOrderMark: true,   // the UTF-8 byte order mark first, which spreadsheets read as UTF-8
    guardFormulas: true,   // texts a spreadsheet would run are kept as text
);
```

Un séparateur d'une autre longueur, un guillemet ou une fin de ligne est refusé par `ExportRefused`.

## L'injection de formules

Un tableur qui ouvre un fichier CSV exécute comme une formule un champ qui commence par `=`, `+`, `-` ou `@`, et certains
exécutent un champ qui commence par une tabulation ou un retour chariot ; une formule peut lire d'autres cellules, ouvrir
des liens ou lancer des programmes. Un champ saisi par quelqu'un d'autre — un titre, un nom, un commentaire — ne doit pas
s'exécuter.

`FormulaGuard::protect()` met une apostrophe devant un texte qui commence par l'un de ces caractères, ou par l'un d'eux
après des espaces. Le tableur montre le texte, apostrophe cachée ou affichée, et n'exécute rien. Un nombre écrit en texte
— `-12`, `+3.5`, `-3,5`, `-1e5` — est laissé tel quel : une colonne de nombres signés reste donc des nombres. Les en-têtes
sont gardés aussi.

Seuls les textes sont gardés : un `int` ou un `float` est écrit comme un nombre. Un fichier relu par un programme plutôt
que par un tableur peut désactiver la garde avec `guardFormulas: false`, quand chaque champ vient de l'application
elle-même.
