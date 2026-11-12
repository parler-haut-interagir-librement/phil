<!-- phil-dev source-sha256=662c555297fee08c382c5a8858e047c7b50b6f9b471492a4c17c8745effe2be6 -->
# Installation

```sh
composer require phil/creation-disclosure
```

Le paquet exige PHP 8.5 ou une version plus récente et `ext-mbstring`. Il n'a aucun service à configurer : chaque
type est une valeur, construite là où on en a besoin. Une application Symfony garde une déclaration dans une colonne
de type `json` avec `toArray()` et la relit avec `fromArray()` ; voir [Déclarer](declaring.md#conserver-une-déclaration).
