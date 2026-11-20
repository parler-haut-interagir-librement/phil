<!-- phil-dev source-sha256=0f68d8fed8d33fba660185459a8beffcb9cf3e4de8450767580bd5819fa45b6d -->
# Correspondance avec ASVS 5.0

Les exigences d'[OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) que la bibliothèque satisfait, avec
le test qui montre chacune, et celles qu'elle laisse à l'application. `phil/upload-scan-bundle` a sa propre
correspondance pour la quarantaine. Le guide du monodépôt,
[ASVS 5.0 : ce que l'application garde](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md),
rassemble ce que chaque paquet laisse.

## Satisfaites par la bibliothèque

| Exigence | Niveau | Comment | Démontrée par |
|---|---|---|---|
| 5.4.3 Fichiers de sources non fiables analysés par un antivirus | 2 | `ClamdScanner` envoie chaque fichier à clamd en flux ; les archives dans des archives sont ouvertes par clamd | `ClamdScannerTest` ; `ClamdTest::testClamdFindsTheEicarFileInsideNestedArchives` contre un vrai clamd |
| 16.5.3 Échouer de façon sûre, jamais ouverte | 2 | Un fichier n'est propre que sur un `OK` explicite : une réponse tronquée, un délai dépassé, un flux qui s'arrête, une réponse inconnue sont des résultats d'erreur, que la politique rejette ou signale | `ClamdScannerTest`, `ClamdResponseTest`, `ScanPolicyTest` |
| 5.2.1 Fichiers d'une taille seulement que l'application peut traiter | 1 | `maxBytes`, le plus gros fichier envoyé ; au-delà, un résultat d'erreur | `ClamdScannerTest` |
| 5.2.3 Archives bornées en taille et en nombre de fichiers | 2 | Satisfaite avec `MaxScanSize`, `MaxFiles`, `MaxRecursion` et `AlertExceedsMax yes` de clamd, sans lesquels une archive au-delà d'une limite ressort propre ([clamd](clamd.md#les-archives-et-leurs-limites)) | `ClamdTest::testAnArchiveBeyondMaxFilesPassesUnlessClamdAlertsOnExceededLimits` |
| 16.4.1 Données encodées contre l'injection | 2 | Une signature lue de clamd est réduite à son ASCII imprimable avant d'atteindre un journal ou un événement | `ClamdResponseTest`, la cible de fuzzing |

## Laissées à l'application

| Exigence | Niveau | Pourquoi elle revient à l'application |
|---|---|---|
| 5.1.1 Les types et les tailles permis, documentés | 2 | La documentation de l'application et ses contraintes `File` ou `Image` |
| 5.2.2 L'extension qui correspond au contenu | 1 | Les `mimeTypes` et `extensions` de la contrainte `File` |
| 5.2.4 Des quotas par utilisateur | 3 | Le stockage de l'application |
| 5.2.5 Archives avec liens symboliques refusées | 3 | Le code qui décompresse une archive ; clamd ne juge pas les liens |
| 5.2.6 Images de trop de pixels refusées | 3 | Le `maxPixels` de la contrainte `Image` |
| La version et les limites de clamd | — | Utilisez ClamAV 1.5.4 ou plus récent (1.4.6 sur la ligne 1.4) avec `AlertExceedsMax yes` |
