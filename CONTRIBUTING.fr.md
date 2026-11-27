<!-- phil-dev source-sha256=d81acfbad2838718b77047ab15d05411aa964747fcae94c6bf5317a4bfc71d1e -->
# Contribuer

[English](CONTRIBUTING.md)

Voici le monodépôt des paquets ouverts de [Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement)
(PHIL). Chaque paquet `phil/*` est développé ici, et son propre dépôt est une copie en lecture seule, scindée de
`packages/<name>/` : **ouvrez les tickets et les demandes de tirage ici**, pas sur un dépôt scindé, que chaque scission
écrase.

## Principes

Les douze principes du [README](README.fr.md#principes) valent pour chaque paquet. Chacun est une règle qu'un
changement suit :

1. **Sûr par défaut.** Les valeurs par défaut sont le choix prudent ; un comportement moins sûr exige une option
   explicite et documentée. Tout ce qui vient de personnes, de robots ou de tiers est validé et borné en taille, en
   profondeur et en temps. Les requêtes sont paramétrées, la sortie est échappée selon son contexte, les secrets et les
   données personnelles n'atteignent jamais les journaux ni les messages. Les paquets sensibles pour la sécurité ont du
   fuzzing et une analyse de propagation (taint) dans leur CI.
2. **Ouvert, en entier.** Chaque paquet porte son propre fichier de licence. Le code d'un paquet lui appartient : du
   code tiers n'entre que comme dépendance Composer déclarée, et les autres projets sont étudiés pour leurs idées. Des
   données de référence de sources ouvertes peuvent être importées par un script, avec leur licence et leur attribution
   consignées à côté.
3. **Natif à Symfony.** Un paquet s'installe avec `composer require` et, quand il faut le brancher, une recette Flex.
   Un bundle a la forme habituelle : son arbre de configuration, des services étiquetés à la manière de Symfony, aucun
   amorçage caché. Sa configuration vit dans `config/packages/`. La bibliothèque emploie un composant Symfony existant
   plutôt que de le réinventer. Les gabarits emploient ce que Twig et twig/extra fournissent déjà ; le code
   front-end passe par AssetMapper et Stimulus, sans étape de construction Node requise.
4. **Bilingue, toutes les langues.** L'anglais est la source de `README.md` et de `doc/` ; la traduction française de
   `README.fr.md` et de `doc/fr/` est mise à jour dans le même changement, et l'outillage la vérifie. Chaque chaîne
   montrée à l'usager est traduisible ; les formats, les pluriels, les adresses et la typographie suivent la locale,
   d'après le CLDR et d'autres références ouvertes.
5. **La vie privée dès la conception.** Aucun pistage ni appel à un tiers sans la configuration explicite du site. Des
   statistiques en agrégats seulement, des adresses IP anonymisées. Les champs personnels sont déclarés pour pouvoir
   être exportés et effacés. Les messages mis en file portent des identifiants, jamais des données personnelles. Un
   message qu'un paquet a publié garde son nom de classe ; celui qui doit être renommé prend
   `#[AsMessage(serializedTypeName: …, serializedTypeNameAliases: […])]`, son ancien nom parmi les alias, pour que les
   messages déjà en file soient encore décodés.
6. **Rapide, mesuré, sûr en mode worker.** Chaque chemin chaud a un banc d'essai, et un changement qui le ralentit
   exige une raison. La complexité est bornée. Aucun état ne survit à une requête, ou il est remis à zéro par
   `kernel.reset`. Le PHP pur est la référence et marche partout (PHP-FPM, CLI, FrankenPHP) ; un accélérateur
   facultatif vit dans son propre paquet, seulement après qu'un banc d'essai en prouve le gain, avec des tests
   différentiels contre la version PHP.
7. **Accessible d'office.** Le balisage emploie l'élément natif qui porte le sens ; ARIA ne comble que ce qui manque au
   HTML. WCAG 2.2 AA est le plancher : usage au clavier, focus visible, focus déplacé et rendu aux dialogues, mouvement
   réduit, contraste vérifié à la construction des jetons. Le mouvement informe ; il ne retarde jamais la personne.
8. **Un vrai besoin.** Un nouveau paquet part d'un besoin qu'un site a aujourd'hui, décrit dans son ticket. Il sort de
   l'incubation quand il règle ce besoin entièrement, pas quand sa feuille de route est longue.
9. **Peu à configurer.** Un bundle démarre avec une configuration vide et des valeurs par défaut prudentes. Chaque
   option est documentée avec sa valeur par défaut et sa raison ; une option que personne ne changerait est retirée.
   `debug:config` montre tout.
10. **Utile seul, meilleur ensemble.** Chaque paquet est installé et testé seul, sans dépendance artificielle. Les
    paquets suivent les conventions communes (clés de configuration, noms Twig, recettes UX, points d'extension,
    déclarations de vie privée), pour que chaque paquet ajouté profite des autres. Les groupes sont offerts comme
    paquets `profile-*`, jamais comme noyau obligatoire.
11. **Prévisible et extensible.** Les points d'extension sont des services étiquetés et des interfaces publiques,
    listés dans `doc/extension-points.md` et stables dès 1.0. Un paquet ne nomme jamais un site ni un service
    particulier qui est le nôtre, pas même dans un exemple. Versionnage sémantique, un fichier Keep a Changelog, des
    changements incompatibles seulement dans une nouvelle version.
12. **Une qualité prouvée.** Plancher : PHP 8.5+ et Symfony 8.2+. Chaque prérequis est contraint à sa dernière mineure
    publiée (`^X.Y`), dans chaque `composer.json` ; PHP reste à 8.5 jusqu'à ce que PHP 8.6.1 soit sorti et que les
    paquets tournent sur 8.6 sans problème. PHPStan à son niveau maximal avec les règles strictes sur chaque PHP de la
    matrice ; PHP-CS-Fixer, Rector, Deptrac et Arkitect ; PHPUnit en échec à toute dépréciation ; Infection à 100 %, et
    un paquet ne sort pas de l'incubation en deçà. Les prochains PHP et Symfony sont testés dès leur première version
    candidate. Le plancher ne monte que dans une nouvelle version de nos paquets.

## Signaler un bogue

- Vérifiez d'abord que le bogue n'est pas déjà signalé.
- Nommez le paquet et donnez sa version, celle de PHP et celle de Symfony, la configuration en cause et le plus petit
  code qui montre le bogue.
- Une vulnérabilité de sécurité n'est pas un rapport de bogue : suivez plutôt la
  [politique de sécurité](SECURITY.fr.md).

## Proposer un changement

Ouvrez un ticket avant un changement important, pour en convenir d'abord de la conception. Ensuite :

1. **Écrivez votre propre code.** Aucun code n'est copié d'un autre projet, même sous une licence compatible :
   un autre projet peut servir de référence à lire, jamais de source à copier, à traduire ou à porter. Une licence
   tierce n'entre dans un paquet que par une dépendance Composer déclarée.
2. **Gardez le paquet indépendant de toute application.** Son code, ses commentaires et sa documentation ne nomment
   jamais une application qui l'utilise ; ce qui est propre à une application relève de la configuration.
3. **Respectez les couches.** Un bundle garde séparés `Domain` (PHP pur, objets-valeurs et règles, sans conteneur ni
   entrée-sortie), `Application` (les services et les ports que l'application implémente) et `Infrastructure` (les
   adaptateurs vers Symfony, Doctrine, Twig…) ; la classe du bundle ne fait que les brancher.
4. **Testez-le.** Chaque changement vient avec ses tests. La couche du domaine est tenue à 100 % de couverture des
   mutations : un mutant qui survit est un test manquant, ou du code que rien ne peut atteindre.
5. **Documentez-le, en anglais.** Mettez à jour le dossier `doc/` du paquet et, quand un sujet s'ajoute, le tableau de
   la documentation de son README. Les miroirs français (`README.fr.md`, `doc/fr/`) suivent le principe 4 ; quand une
   personne qui contribue n'écrit pas le français, un mainteneur les complète avant la version.
6. **Gardez-le sûr par défaut.** Trois règles valent dans chaque paquet :
   - un objet-valeur de données personnelles — une adresse, un numéro de téléphone, un nom — ne copie jamais l'entrée
     dans le message d'une exception, qui peut atteindre un journal : le message nomme la raison, et l'entrée, quand
     un appelant en a besoin, reste dans une propriété ;
   - un bundle qui appelle un service tiers accepte l'identifiant de son client HTTP comme option, et fixe des délais
     par défaut sur chaque appel (`timeout` et `max_duration`), que sa configuration peut changer ;
   - une clé qui signe ou qui hache est dérivée pour son usage du secret de l'application, par
     `hash_hkdf('sha256', $secret, 32, 'phil_<bundle>.<usage>')`, jamais le secret lui-même : deux usages ne partagent
     jamais une clé, pour qu'une signature ou un pseudonyme fait pour l'un ne corresponde jamais dans un autre.
7. **Gardez les tableaux simples.** Un tableau de données a une rangée d'en-têtes de colonnes, chacun un `th` avec
   `scope="col"`, et un en-tête de rangée, un `th` avec `scope="row"`, au début de chaque rangée qui en a un. Une
   cellule d'en-tête ne s'étend jamais sur plusieurs colonnes ou rangées (`colspan` ou `rowspan` sur un `th`), un
   `thead` contient une rangée, et l'attribut `headers` n'est pas employé : les lecteurs d'écran annoncent inégalement
   les en-têtes fusionnés et à plusieurs niveaux, et aucun n'entend `headers` de façon fiable. Des colonnes qui vont
   ensemble deviennent un tableau à part, avec son `caption`, ou une colonne de plus ; une rangée de totaux met son
   en-tête seul dans la première colonne et laisse les autres cellules vides. `phil/accessibility-check` avertit des
   cellules d'en-tête fusionnées et des en-têtes à plusieurs rangées (`table-header-levels`), et un passage manuel avec
   un lecteur d'écran vérifie le reste.
8. **Passez les vérifications.** Depuis la racine du monodépôt, après `composer install` et `composer hooks`, les
   vérifications de qualité s'exécutent sur chaque paquet — PHP-CS-Fixer, Rector, PHPStan à son niveau maximal,
   deptrac, Arkitect, PHPUnit et Infection — et `composer packages` installe chaque paquet seul, vérifie ses
   dépendances déclarées et exécute ses tests. Une demande de tirage est fusionnée quand toutes les vérifications
   passent.

Les vérifications sont des scripts Composer de la racine : `composer gates` les exécute dans l'ordre, et chacune
s'exécute seule, par exemple `composer phpstan` ou `composer test`. Pour vérifier un paquet seul, depuis son dossier :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Fuzzing

Un paquet dont le code lit une entrée que personne n'a écrite — un analyseur, un décodeur, un lecteur de fichiers ou
de jetons — déclare des cibles de fuzzing dans `tests/Fuzz/<target>.php`, exécutées par `composer fuzz` depuis la
racine du monodépôt, chacune selon son budget de temps. Une cible part des germes de `tests/Fuzz/corpus/<target>/`.

**Seuls les germes nommés sont commités.** Un germe est une petite entrée écrite exprès et nommée d'après ce qu'elle
contient, comme `seed-1.json`, `seed-empty.json` ou `two-records-seed.txt` ; une entrée qui a déjà fait échouer une
cible est gardée comme germe de régression, `regression-<hash>.txt`. Chaque cible garde au moins un germe, pour partir
d'une entrée valide. Le fuzzer ajoute au corpus les entrées qu'il trouve sous leur MD5, comme
`0f3a…9c.txt` : ces fichiers sont propres à l'exécution, ignorés par le `.gitignore` du monodépôt, et jamais commités.
Pour en garder un, renommez-le d'après ce qu'il montre. Le fuzzer raccourcit aussi les entrées du corpus au fil de
l'exécution, un germe nommé parmi elles, et enregistre l'entrée raccourcie sous son MD5 : un germe qu'une exécution
montre comme supprimé se restaure avec `git restore packages/<name>/tests/Fuzz/corpus`, et les fichiers générés se
retirent avec `git clean -X packages/<name>/tests/Fuzz/corpus`.

## Licence

En contribuant, vous acceptez de placer votre contribution sous la licence MIT du paquet, dont son `LICENSE` nomme le
titulaire.

## Code de conduite

Toute personne qui participe suit le [code de conduite](CODE_OF_CONDUCT.fr.md).
