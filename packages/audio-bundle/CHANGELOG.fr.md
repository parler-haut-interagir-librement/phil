<!-- phil-dev source-sha256=742f9acbea60cabb3f377843f5f11ea5304fde0aca630db67aa2fb9dc9a9a358 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/audio-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

### Corrigé

- La passe de compilation qui retire des handlers de Messenger s'exécute avant celle de `symfony/messenger`, que Symfony
  8.2.0-RC1 enregistre dans son propre bundle à la priorité par défaut : la construction du conteneur n'échoue plus sur
  un localisateur de handlers qui nomme un service retiré.

## 0.1.1-alpha - 2026-11-18

### Modifié

- `phil/media-bundle` 0.2 est accepté à côté de 0.1.1.

## 0.1.0-alpha - 2026-11-15

### Ajouté

- La configuration `phil_audio` : les outils, `null` ou `ffmpeg`, les programmes et les limites de ffmpeg, les versions
  par nom — refusées quand aucun encodeur ne les accepte —, la forme d'onde, la politique d'écoute par défaut, le
  stockage et le sous-dossier des recettes du kit.
- Les tâches : `AudioJobs::schedule()` et les messages `TranscodeAudio` et `ComputeWaveform`, des identifiants et des
  instants seulement ; leurs handlers, qui suppriment leurs fichiers temporaires quoi qu'il arrive et échouent pour de
  bon sur un fichier disparu, une version inconnue ou un fichier refusé ; aucune tâche sans outil ou sans bus de
  messages.
- Les ports `AudioFiles` et `RenditionStore`, et leurs adaptateurs sur phil/media-bundle : des copies locales aux noms
  aléatoires, créées pour la seule copie, lisibles par leur propriétaire seulement, envoyées en flux depuis le stockage
  par `MediaLibrary::readOriginal()` de phil/media-bundle 0.1.1 ou plus récent (un conflit avec les versions plus
  anciennes), supprimées quand la copie échoue, un original manquant étant un fichier disparu et un stockage ou un
  disque défaillant une erreur que la tâche peut réessayer ; les résultats sous `<prefix>/<medium>/`, des clés qui ne
  quittent jamais leur dossier.
- Le lecteur `PhilAudio:Player` : l'élément audio du navigateur, les fichiers que la personne peut entendre, une phrase
  avant un achat, les chapitres et les transcriptions comme liens vers leurs instants, dessiné avec la recette Button du
  kit.
- Le contrôleur Stimulus `player` et sa feuille de style : la forme d'onde en SVG, la recherche depuis un chapitre, une
  réplique ou la forme d'onde, le chapitre et la réplique courants marqués.
- Les textes du lecteur en anglais, en français, en espagnol, en allemand, en italien et en portugais.
- La recette Flex `recipe/0.1` : le bundle et sa configuration.
- Le squelette du paquet, sa documentation dans `doc/`, `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
