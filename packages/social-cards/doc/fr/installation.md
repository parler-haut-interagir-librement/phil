<!-- phil-dev source-sha256=bb28422dc0c65c497c241e415672e06dee008e9ab52e42ca3512ac2f8dd8b673 -->
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

Une application Symfony installe phil/social-cards-bundle, qui exige cette bibliothèque.
