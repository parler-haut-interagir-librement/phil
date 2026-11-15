<!-- phil-dev source-sha256=55bd115b3d00f4224a9a29750d639a4f54c7e2a26de40662e5f67e961af5cc81 -->
# Tests

## Les tests du paquet

```sh
vendor/bin/phpunit packages/audio-bundle/tests
```

La suite unitaire s'exécute sans noyau : la configuration et les services qu'enregistre l'extension, les passes, les
tâches avec outils et stockage en mémoire — ffmpeg remplacé par un transcodeur qui écrit sa source en majuscules —, et
les adaptateurs de phil/media-bundle sur sa bibliothèque en mémoire.

La suite fonctionnelle démarre une petite application — FrameworkBundle avec Messenger et le traducteur, TwigBundle,
TwigComponentBundle avec la recette Button copiée là où `ux:install` la mettrait, et le bundle — et dessine le lecteur
pour une piste entière, un extrait, une piste avant un achat et une piste nue, en anglais et en français. Elle est
ignorée là où le répartiteur d'événements n'est installé que pour le développement, comme quand le paquet est installé
seul.

Le paquet est tenu à 100 % de score de mutation couvert par Infection.

## Le contrôleur

```sh
node --import ./tests/js/register.mjs --test tests/js
```

Le contrôleur s'exécute sous le lanceur de tests de Node, Stimulus résolu en doublure : le tracé des barres, le chapitre
et le repère courants, le positionnement depuis un lien, un lien sans instant suivi, un clic sur la forme d'onde.

## Avec un vrai ffmpeg

Les suites n'ont pas besoin de ffmpeg. Pour exécuter les tâches pour de vrai, réglez `tools: ffmpeg` dans une
application dotée d'un worker et téléversez un fichier : les versions et `peaks.json` apparaissent sous le préfixe du
stockage.
