<!-- phil-dev source-sha256=e3428554e753ae2a90e86afba00f91735e7ead3955430df9fb5c4f5834fee5fb -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `WeasyPrintEngine` | Les arguments donnés à la commande, le document sur l'entrée standard, le PDF sur la sortie standard, le code de sortie et la sortie d'erreur d'un échec, sa borne, une réponse qui n'est pas un PDF, le délai, une commande absente, la version lue et comparée |
| `PdfOptions` | Chaque option et son argument, leur ordre, chaque intention de sortie acceptée et refusée, PDF/X sans profil, les bornes de la résolution et de la qualité JPEG |
| Activation | La ligne unique de `services.yaml` lue et compilée par Symfony, le moteur sous le port avec ses valeurs par défaut, un service `LocalAssets` qui lui est donné par câblage automatique |
| `LocalAssets` | Chaque élément et attribut lu, les feuilles de style avec toutes les sortes de guillemets, l'encodage pour cent, chaque référence laissée telle quelle — répertoires parents, liens hors du répertoire, chemins absolus, adresses, requêtes, fragments, octets NUL, répertoires, extensions non prises, fichiers illisibles —, les limites, un document laissé intact octet par octet quand rien n'est pris |

Les tests unitaires, sous `tests/Unit`, exécutent un script shell qui remplace la commande et garde ses arguments et
son entrée. Ils n'ont pas besoin de WeasyPrint, mais d'un shell POSIX.

```sh
vendor/bin/phpunit packages/paged-media-weasyprint/tests/Unit
```

Le test sous `tests/Integration` exécute la vraie commande, trouvée dans le `PATH`, et est sauté sans elle. Il imprime
une page, vérifie la version, vérifie qu'un fichier hors du répertoire des ressources n'atteint le PDF ni comme image ni
comme pièce jointe, qu'une image distante n'est pas téléchargée, et qu'un PDF/X-4 a son intention de sortie, sa boîte de
rognage et sa boîte de fond perdu ; ce dernier test prend un profil CMYK du système, et est sauté sans lui.

Les références d'un document sont aussi soumises au fuzzing, depuis la racine du monodépôt :

```sh
composer fuzz -- assets
```
