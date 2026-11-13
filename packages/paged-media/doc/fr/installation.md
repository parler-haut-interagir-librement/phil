<!-- phil-dev source-sha256=e6500bacb9867b1facb5d72a4e4c8e548318a9db6c596f81d61549a158ab1f6c -->
# Installation

## Prérequis

| Prérequis | Raison |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, énumérations avec constantes |
| `ext-ctype` | Les sélections de pages, les couleurs et les comptes sont vérifiés caractère par caractère |
| `ext-mbstring` | Le texte est découpé en caractères et converti en Windows-1252 et en UTF-16 |
| `ext-zlib` | Les flux sont compressés, et les images PNG transparentes décompressées |
| `ext-uri` | Les adresses de service de Gotenberg sont lues par l'analyseur d'URL WHATWG |
| `phil/url` | Les adresses publiques que Gotenberg va chercher, téléchargements et autorités d'horodatage, sont lues et vérifiées |

Le moteur d'écriture natif n'a aucune autre dépendance : aucun binaire, aucun service, aucun fichier de police à lui.

Le client Gotenberg a besoin de Symfony HttpClient, que la bibliothèque suggère, et d'un Gotenberg 9 avec qui
communiquer :

```sh
composer require symfony/http-client
docker run --rm -p 3000:3000 gotenberg/gotenberg:9
```

## Installer

```sh
composer require phil/paged-media
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Une application Symfony crée un `Document` ou un
`Gotenberg` là où elle en a besoin, les enveloppe dans ses propres services, ou installe `phil/paged-media-bundle`, qui
configure les deux derrière un seul service.
