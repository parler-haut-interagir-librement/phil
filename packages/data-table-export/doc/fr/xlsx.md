<!-- phil-dev source-sha256=7b8b0a579c1bc09fc280381b2963d2ff8bc62a6840014a1a4069c42baf1d1354 -->
# XLSX

`XlsxWriter` écrit un classeur Office Open XML d'une feuille de calcul, lisible par LibreOffice, Excel, Numbers et
Google Sheets, avec ext-zip et du XML seulement.

```php
new XlsxWriter(
    directory: null,                     // where the temporary files go, sys_get_temp_dir() when null
    rowLimit: XlsxWriter::ROW_LIMIT,     // the most rows, headings included: 1 to 1,048,576
);
```

## Le classeur

- Les en-têtes sont la première ligne, en gras, figés au-dessus des lignes qui défilent.
- Un texte est une chaîne en ligne : jamais une formule, donc rien de ce qu'une personne a saisi ne s'exécute à
  l'ouverture du fichier. Il est limité à de l'UTF-8 valide et aux caractères qu'autorise XML 1.0, et coupé aux
  32 767 caractères que contient une cellule.
- Un nombre et un booléen gardent leur sorte ; `INF` et `NAN`, qu'un classeur ne peut pas contenir, sont écrits en
  texte.
- Une date est un numéro de série de jours, au format `yyyy-mm-dd` à minuit et `yyyy-mm-dd hh:mm:ss` autrement. Une date
  antérieure au 1er mars 1900, que les tableurs comptent de travers, est écrite en texte.
- Le titre nomme la feuille de calcul : les caractères `[ ] : * ? / \` et les caractères de contrôle deviennent des
  espaces, une apostrophe à l'une ou l'autre extrémité est retirée, il est coupé à 31 caractères, et il vaut `Sheet1`
  quand il ne reste rien.
- Chaque entrée de l'archive porte la même date : la même feuille donne donc toujours les mêmes octets.

## Mémoire et fichiers temporaires

Les lignes sont écrites dans un fichier temporaire par morceaux de 64 Kio, puis les parties sont compressées dans un
second fichier temporaire, copié dans le flux. La mémoire reste celle d'un morceau, quel que soit le nombre de lignes ;
le disque contient la feuille de calcul en double pendant l'écriture du fichier. Les deux fichiers temporaires sont
supprimés à la fin de l'écriture, refusée ou non.

## Limites

Une feuille de plus de lignes que `rowLimit` — 1 048 576 au plus, la limite d'un tableur — ou d'une ligne de plus de
16 384 cellules s'interrompt par `ExportRefused`, et rien n'est écrit dans le flux : le classeur n'est copié qu'une fois
complet.
