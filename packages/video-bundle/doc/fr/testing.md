<!-- phil-dev source-sha256=6faee0197c7748ee8523c185ea3d3baaabf8629d7a474dde7f5de7baea5c58c3 -->
# Tests

## Les tests du paquet

```sh
vendor/bin/phpunit packages/video-bundle/tests
```

La suite unitaire s'exécute sans noyau : les vidéos et leurs refus, les lecteurs de YouTube et de Vimeo sans témoin, les
visionnements et les cartes de chaleur, le suiveur avec et sans consentement, les stockages, chaque réponse du
contrôleur, le WebVTT servi, et la configuration.

La suite fonctionnelle démarre une petite application — FrameworkBundle, TwigBundle avec ses extras, TwigComponentBundle
avec les recettes Video, Image et Button copiées là où `ux:install` les mettrait, le bundle de phil/ux-kit, et le bundle
avec le suivi activé et un consentement que les tests basculent — et dessine une vidéo hébergée et une vidéo liée, en
anglais et en français, puis envoie un visionnement à la route. Elle est ignorée là où le répartiteur d'événements n'est
installé que pour le développement.

Le paquet est tenu à 100 % de score de mutation couvert par Infection.

## Fuzzing

| Cible | Ce qu'elle soumet au fuzzing |
|---|---|
| `linked-url` | `LinkedVideo::fromUrl()` : une vidéo de YouTube ou de Vimeo sans témoin, ou `InvalidVideo` |
| `progress` | `ProgressController` sur n'importe quel corps : `204`, `400` ou `413` |

```sh
composer fuzz -- linked-url progress
```

## Le contrôleur

```sh
node --import ./tests/js/register.mjs --test tests/js
```

Le contrôleur s'exécute sous le lanceur de tests de Node, Stimulus résolu en doublure : les parties d'un instant, le
visionnement envoyé seulement avec consentement et une seule fois, la page cachée, le flux HLS là où il joue, le
positionnement, et la façade chargée seulement à l'appui.
