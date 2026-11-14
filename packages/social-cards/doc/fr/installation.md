<!-- phil-dev source-sha256=a962b05f339554cbe7696c1d2a32ade642568ad49d3428dc89c31fc18c9e8bcd -->
# Installation

```sh
composer require phil/social-cards
```

| Prérequis | Utilisé pour |
|---|---|
| PHP 8.5 ou plus récent, `ext-uri` | L'extension URI de PHP, qui analyse et résout les adresses comme le font les navigateurs |
| `ext-ctype` | Les tailles lues dans les balises d'une page |
| `ext-dom`, `ext-libxml` | L'analyseur HTML5 qui lit les pages d'autres sites |
| `ext-gd`, avec FreeType | Le dessin des cartes et la mesure de leur texte |
| `ext-intl` | La coupure des lignes d'un titre selon les règles de sa langue, et ses caractères |
| `ext-mbstring` | Le décompte et la coupe des textes en caractères |
| `phil/fonts` | La lecture des polices d'un gabarit : leur format, leur famille, leur licence et leur empreinte |
| `phil/url` | La vérification des adresses lues dans les pages d'autres sites : un nom d'hôte public, jamais une adresse IP ni `localhost` |
| `ext-imagick`, suggérée | `ImagickCardRenderer` : avec ImageMagick compilé avec libraqm, le texte de l'arabe, de l'hébreu et des écritures de l'Inde mis en forme et disposé dans son sens |

Une application Symfony installe phil/social-cards-bundle, qui exige cette bibliothèque.
