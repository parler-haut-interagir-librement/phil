<!-- phil-dev source-sha256=c0218901b872b9c9194f3e65d55a1b11140eb48810dcecb233addecae51505f6 -->
# Vie privée et accessibilité

## Ce que portent les messages

Le message d'une tâche est un identifiant, le nom d'une version et deux instants. L'audio ne voyage jamais dans un
message : le worker le récupère dans le stockage par son identifiant. Un transport — une base de données, la file
d'attente d'un autre service — ne garde rien de ce qui a été dit, ni de qui l'a dit.

## Ce qui quitte le serveur

Rien : ffmpeg s'exécute sur les workers, fermé au réseau (voyez phil/audio). Le lecteur récupère les propres fichiers du
site.

## Les étiquettes d'un fichier

Une version ne porte aucune des étiquettes du fichier téléversé : le nom d'un enregistreur pour l'auteur, un lieu, un
appareil restent dans l'original, que l'application garde privé.

## Ce que le lecteur doit à ses auditeurs

- La commande est le `<audio>` du navigateur, nommé par le titre : atteint au clavier, lu par les lecteurs d'écran,
  jouable sans JavaScript (WCAG 2.1.1, 4.1.2).
- Rien ne joue avant que l'auditeur le demande (WCAG 1.4.2) ; seule la durée est récupérée au préalable.
- Une transcription, dans sa propre langue, à côté de l'audio (WCAG 1.2.1) ; chaque repère et chaque chapitre atteint au
  clavier, et sans JavaScript comme lien vers son instant.
- La forme d'onde est de la décoration, cachée aux lecteurs d'écran ; le chapitre et le repère courants sont marqués par
  `aria-current`, pas par la couleur seule.
- Un auditeur qui ne peut pas entendre la piste en apprend la raison, en une phrase, plutôt que de voir une commande qui
  ne fait rien.
