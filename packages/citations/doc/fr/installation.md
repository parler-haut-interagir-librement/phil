<!-- phil-dev source-sha256=99db220a54faf08d243300526155b2b0059579f0c0035077a6f0660519953724 -->
# Installation

```sh
composer require phil/citations
```

Le paquet exige PHP 8.5 ou une version plus récente, avec `ext-intl` (le classement des bibliographies, la
normalisation Unicode, la translittération des identifiants fabriqués), `ext-mbstring` et `ext-ctype`.

Aucune configuration ni aucun service n'est nécessaire. Chaque classe ne garde rien entre deux textes : un lecteur, un
processeur ou un style construit une fois peut servir toutes les requêtes d'un *worker*. Le paquet ne contient aucun
fichier de style CSL et n'appelle aucun service extérieur ; les références viennent de l'application, de CSL-JSON, de
BibTeX ou de RIS.
