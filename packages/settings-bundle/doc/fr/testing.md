<!-- phil-dev source-sha256=dbbea20c74ba8a12204d93b975f5aee2dcc2f2e4bd743a91316e0525ce98f7af -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau : le dépôt DBAL sur SQLite en mémoire, le
formulaire sur le vrai composant de formulaires, les pages sur Twig avec des gabarits en mémoire, le reste sur des jeux
de données d'essai en mémoire ou qui enregistrent. La suite fonctionnelle, sous `tests/Functional`, démarre une
application avec FrameworkBundle et TwigBundle. Elle produit le rendu des préférences en français, enregistre les
préférences d'affichage d'un visiteur dans son témoin et les relit sur l'élément racine de la page, enregistre celles
d'un utilisateur, et refuse un formulaire venu d'une autre origine par la vraie protection CSRF sans état. Une seconde
application de la suite fonctionnelle ajoute SecurityBundle, ses utilisateurs en mémoire se connectant par HTTP Basic :
les réglages du site refusés à un visiteur et à un membre, chaque réglage montré à son seul rôle, un champ d'un autre
rôle refusé, l'aperçu qui n'enregistre rien, les préférences enregistrées sous l'utilisateur que nomme Symfony
Security, et les migrations exécutées une fois depuis la console. Chacune de ses requêtes traverse le noyau comme le
sert un *worker*, terminée, puis les services sont réinitialisés. Les tests Node couvrent le contrôleur Stimulus.

Dans le monodépôt, les tests s'exécutent sur PHP 8.5 et 8.6, et Infection maintient le paquet à 100 % de mutants
couverts.

## Les bancs d'essai

`tests/Benchmark/SettingsBench.php` mesure ce que fait chaque page : les attributs de l'élément racine pour un
utilisateur, sur une nouvelle requête (les valeurs lues dans une table de mille utilisateurs) et au sein d'une même
requête, la même chose pour un visiteur par son témoin, et la lecture du témoin seule. Sur PHP 8.5 et un Apple M1 Pro, les attributs de l'élément racine prennent environ
17 µs sur une nouvelle requête, 5 µs au sein d'une même requête et 4 µs pour un visiteur ; le témoin est lu en 5 µs.

```sh
composer bench -- --filter=SettingsBench
```

## Fuzzing

Le lecteur du témoin des préférences est soumis au fuzzing : quoi qu'il lise, il ne lance jamais d'exception, ne
retourne que des valeurs que ses réglages acceptent, et ce qu'il retourne s'écrit et se relit avec les mêmes valeurs.

```sh
composer fuzz -- cookie --budget=60
```
