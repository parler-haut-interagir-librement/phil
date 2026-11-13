<!-- phil-dev source-sha256=79dca733c963d97661dfebafb645aeec95649918cb4642cf382f8f61019ff8a4 -->
# Exceptions

Tout ce que le paquet rapporte au moment d'imprimer est une `Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed`,
une `RuntimeException`.

| Propriété | Ce qu'elle contient |
|---|---|
| `exitCode` (`?int`) | Le code avec lequel la commande s'est terminée ; `null` quand elle a été arrêtée, n'a pas pu démarrer ou n'a jamais été exécutée |
| `errorOutput` (`string`) | Ce que la commande a écrit sur sa sortie d'erreur, jusqu'à 64 Kio |

Le message nomme l'échec et omet le document. `errorOutput` peut citer les adresses du document : journalisez-la là où
l'application garde ce genre de données.

| Situation | `exitCode` | Message |
|---|---|---|
| La commande s'est terminée sur une erreur | Son code | `WeasyPrint stopped with exit code N.` |
| Elle a dépassé le délai | `null` | `"<binary>" ran out of time.` |
| Elle a été tuée, ou n'a pas pu démarrer | `null` | `"<binary>" did not run to its end: …` |
| Elle s'est bien terminée sans PDF sur sa sortie | `0` | `WeasyPrint did not write a PDF.` |
| `--version` n'a donné aucun numéro | `0` | `WeasyPrint did not say which version it is.` |
| `assertSupported()` sur une commande plus ancienne | `null` | `WeasyPrint X is older than the Y this package needs.` |
| Le répertoire des ressources est absent | `null` | `The assets directory "…" does not exist.` |
| Un fichier dépasse `$maxFileBytes` | `null` | `The asset "…" is over the limit of N bytes.` |
| Les fichiers dépassent `$maxTotalBytes` | `null` | `The assets of the document are over the total limit.` |

Un délai ou une limite inférieurs à 1, et une option de `PdfOptions` hors limites, lancent `InvalidArgumentException` à
la construction de l'objet : c'est une erreur de branchement.

Une commande introuvable se termine avec le code de sortie 126 ou 127 du shell qui la lance.
