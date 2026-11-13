<!-- phil-dev source-sha256=ebd7363521f44ce96da136b9a9183b6875720c05f9e47405dfde837addb1c4ee -->
# phil/paged-media-weasyprint

**Du HTML et du CSS imprimés en PDF par WeasyPrint pour phil/paged-media-bundle, le réseau et le disque hors de sa
portée.**

phil/paged-media-weasyprint est le moteur WeasyPrint de `phil/paged-media-bundle`. `WeasyPrintEngine` implémente le
port `PrintEngine` du bundle en appelant la commande `weasyprint` : le document entre par son entrée standard, le PDF
sort par sa sortie standard, et un échec lance `WeasyPrintFailed` avec le code de sortie et la sortie d'erreur.

Utilisez-le quand un document a besoin de ce que fait un moteur de médias paginés — en-têtes courants, compteurs de
pages, notes de bas de page, colonnes, CSS Paged Media — et que la machine peut exécuter WeasyPrint. Le moteur
d'écriture natif du bundle convient à un document dessiné dans le code, et n'a besoin d'aucun moteur.

C'est un paquet à part pour que `phil/paged-media` et `phil/paged-media-bundle` n'exigent pas Python sur la machine.

La commande ne peut télécharger que des adresses `data:` : un document ne peut atteindre ni un fichier du serveur ni
une adresse de son réseau, quel que soit son auteur, et une image distante n'est jamais téléchargée. `LocalAssets`
donne à un document les images, les polices et les profils de couleur d'un répertoire, en les écrivant dans le document
avant le lancement de la commande. Voyez [Sécurité](doc/fr/security.md).

`PdfOptions` choisit ce qu'est le PDF : PDF/A, PDF/UA ou PDF/X avec le profil ICC de sa condition d'impression, balisé,
avec des champs de formulaire, ses images rééchantillonnées. Voyez [Sortie PDF](doc/fr/pdf-output.md).

## État

| Partie | État |
|---|---|
| `WeasyPrintEngine` | Implémenté et testé |
| `LocalAssets` : images, polices et profils ICC d'un répertoire | Implémenté et testé |
| `PdfOptions` et `PdfVariant` : PDF/A, PDF/UA, PDF/X et son intention de sortie, balises, formulaires, images | Implémenté et testé, et exécuté contre WeasyPrint 70 |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, la version de WeasyPrint et sa détection, le branchement du service. |
| [Sortie PDF](doc/fr/pdf-output.md) | La norme du PDF, son intention de sortie et son profil ICC, les balises, les formulaires, les images. |
| [Sécurité](doc/fr/security.md) | Ce que la commande peut télécharger, le répertoire des ressources, les limites. |
| [Exceptions](doc/fr/exceptions.md) | Ce que rapporte `WeasyPrintFailed`. |
| [Points d'extension](doc/fr/extension-points.md) | Les arguments qu'une application choisit, et ce qu'elle remplace. |
| [Tests](doc/fr/testing.md) | La fausse commande des tests unitaires, et le test de la vraie. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/paged-media-weasyprint
```

Une ligne de `config/services.yaml` en fait le moteur du bundle :

```yaml
services:
    # …
    Phil\PagedMediaBundle\Application\Contract\PrintEngine: { class: Phil\PagedMediaWeasyprint\WeasyPrintEngine }
```

```php
$output = $this->pagedMedia->printHtml(new PrintableHtml($html, [$css], 'fr', 'The book'), 'book.pdf');
```

## Prérequis

PHP 8.5 ou plus récent avec `dom`, `libxml` et `sodium` ; `symfony/process` 8.2 ; `phil/paged-media-bundle` 0.1 ; la
commande `weasyprint`, en version 70 ou plus récente, sur la machine qui imprime.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
