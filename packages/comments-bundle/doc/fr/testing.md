<!-- phil-dev source-sha256=ea18c5cbb85687b4f7b2c0a29b1fe6fe8c398748739d7a85406ab3df8dcb7927 -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les ancres et leurs limites, le texte du HTML en ligne et un passage qui y est remplacé, le
passage retrouvé — en place, déplacé, parmi des endroits égaux, par champ, par bloc et par document, par contexte,
vers une autre occurrence quand la sienne a disparu, au-delà du plan de base, avec des contextes de caractères larges —,
les fils sur des passages et sur la page, les commentaires et leurs états, les membres jamais nommés par une adresse de
courriel, et `Comments` avec sa politique, ses événements, un réancrage qui laisse la page tranquille et les
suggestions appliquées à un document UJO. Il couvre aussi les textes d'un document UJO et leurs chemins, le même
contrat sur les deux stockages (celui de Doctrine sur SQLite en mémoire, ses tables créées à partir du mappage, chaque
entité relue depuis la base de données) et le stockage en mémoire vidé entre deux requêtes, chaque réponse des points de
terminaison et les jetons CSRF qu'ils acceptent et refusent, `ClientConfig` avec les rôles de WAI-ARIA 1.3, le
composant de la page pour chaque genre de lecteur et les refus de son contrôleur, les noms des recettes du kit, ainsi
que la configuration, les services, les passes de compilation et les réglages ajoutés en amont par le bundle — les
chemins d'AssetMapper dans l'extension d'AssetMapperBundle, rien sans lui.

`tests/Functional` démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite et le bundle. Il fait jouer deux
auteurs et un réviseur sur une page par les routes : un fil avec une suggestion, une réponse, des refus, la liste, le
réancrage après une modification, la suggestion appliquée, un commentaire modéré, la réouverture et la suppression,
chaque requête avec son jeton CSRF et son témoin ; les requêtes qui n'en ont pas sont refusées. Le même noyau, avec
TwigBundle, TwigComponentBundle et les recettes du kit PHIL UX copiées là où `ux:install` les met, dessine un article
avec `PhilComments:Page` et y joue une conversation par ses seuls formulaires simples — ouvrir, répondre, modifier,
résoudre, modérer, rouvrir, supprimer —, puis personne de connecté, un formulaire d'un autre site, une adresse changée,
un texte vide, une action inconnue, un fil absent et une modification refusée, et les rôles de WAI-ARIA 1.3 activés.
Quand le bundle est installé seul, là où le répartiteur d'événements est un prérequis de développement, la suite
fonctionnelle est sautée.

`tests/js` exécute les modules purs avec le lanceur de tests de Node : les caractères, le texte du HTML en ligne, les
remplacements, le passage retrouvé avec les mêmes cas que les tests PHP, les chemins des champs, les points DOM et les
positions sur de petits nœuds factices, les suggestions appliquées à un document, les requêtes et leur jeton CSRF, un commentaire sur la page, les éléments du
panneau — les fils sur la page, les fils orphelins, le formulaire de la page, les rôles de WAI-ARIA 1.3 écrits seulement
sur demande —, et les libellés.

Depuis la racine du monodépôt, puis depuis le bundle :

```sh
vendor/bin/phpunit packages/comments-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/comments-bundle
```

Chaque ligne de `src/` est tenue par le seuil de mutation du monodépôt : chaque mutant est tué.

## Bancs d'essai

`tests/Benchmark/AnchoringBench.php` mesure la recherche d'un passage dans un document de cent blocs — en place, déplacé
vers un autre bloc, disparu —, dans un champ de cent mille caractères où un passage de quatre lettres apparaît à chaque
endroit, avec chevauchement, et dans un champ de cinquante mille caractères de deux octets, ainsi que les textes d'un
document de cent blocs. Sans pilote de couverture, un passage dans le document coûte environ un dixième de milliseconde,
les textes du document quelques millisecondes, et les champs hostiles quelques centaines de millisecondes, en croissant
avec leur longueur.

```sh
composer bench -- packages/comments-bundle/tests/Benchmark
```

## Fuzzing

La cible `reanchoring` lit des entrées que personne n'a écrites : la première ligne est un passage, le reste du HTML en
ligne coupé en champs à chaque ligne vide. Lire le HTML, trouver le passage et le remplacer ne lèvent jamais d'exception,
sauf pour les passages qu'une ancre refuse ; un passage trouvé est le passage lui-même, à sa position, dans son champ, et
le remplacer par lui-même laisse le texte de son champ tel qu'il était.

```sh
composer fuzz -- reanchoring --budget=60
```

## Les vérifications dans un navigateur

Le contrôleur est vérifié à la main sur une page avec Stimulus, l'éditeur par blocs, le contrôleur et les routes.
Chaque vérification dit quoi faire et ce qui doit se produire.

| Domaine | Faire | Attendu |
|---|---|---|
| Sélection | Sélectionnez deux mots dans un paragraphe | La barre d'outils apparaît sous eux ; une sélection sur deux paragraphes n'affiche rien |
| Fil | *Comment*, écrivez, envoyez | Le fil apparaît dans le panneau et les mots sont surlignés |
| Suggestion | Sélectionnez un mot, *Suggest*, changez le remplacement, envoyez | Le panneau montre le mot barré et le remplacement |
| Suivi | Tapez des mots avant le passage surligné | Le surlignage reste sur le passage |
| Disparition | Supprimez le passage | Son fil indique qu'il est orphelin et passe à la fin du panneau |
| Ailleurs | Supprimez le passage alors que les mêmes mots restent dans un autre bloc, enregistrez et réancrez | Le fil suit les autres mots, surlignés à cet endroit |
| Page | Écrivez dans *Comment on the page*, envoyez | Le fil apparaît sous *On the page* et le formulaire se vide ; il n'a ni citation ni surlignage |
| Sans éditeur | Une page avec le contrôleur et sans éditeur | La section de la page et son formulaire ; les fils sur des passages montrent leur citation, seuls ceux que le serveur a rendus orphelins le disent |
| Navigation | Cliquez sur un mot surligné, puis sur le passage d'un fil | Le fil est encadré ; l'éditeur défile jusqu'au passage |
| Acceptation | *Accept* une suggestion | Le passage est remplacé dans l'éditeur, le fil est résolu ; enregistrer le formulaire garde le changement |
| Émoji | Commentez un mot qui suit un émoji | Le surlignage couvre exactement le mot |
