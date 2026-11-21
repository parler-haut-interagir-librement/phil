<!-- phil-dev source-sha256=d7e1608094fdfdf997271aadcb885127ade6d434b4e49b4b7db236e88ea24ac2 -->
# Sécurité

Les textes peuvent venir de n'importe qui. Le traducteur les tient à l'écart de l'interpréteur de commandes et borne ce
qu'ils coûtent.

- **Aucun interpréteur de commandes.** Le programme est démarré par `symfony/process` avec ses arguments en liste :
  `lou_translate`, `--forward` et les tables d'affichage et de traduction. Les textes n'atteignent jamais la ligne de
  commande ; ils vont sur son entrée standard, un par ligne, leurs caractères de contrôle changés en espaces, pour
  qu'aucun texte ne puisse ajouter une ligne.
- **Aucun chemin de l'extérieur.** Le programme est un nom cherché dans le `PATH`, ou un chemin que donne l'application.
  Une table est un nom de fichier, vérifié contre lettres, chiffres, `.`, `_` et `-` : pas de `/`, pas de `..`, rien
  qui commence par `-`. Les répertoires de `LOUIS_TABLEPATH` sont la configuration de l'application.
- **Un temps borné.** `$timeout`, 30 secondes par défaut, arrête un lot.
- **Une taille bornée.** `$maxInputBytes`, 4 Mio par défaut, refuse un lot avant que le programme démarre ;
  `$maxOutputBytes`, 32 Mio par défaut, arrête le programme dès qu'il écrit davantage, sortie d'erreur comprise ; la
  sortie d'erreur gardée dans une exception fait au plus 64 Kio.
- **Des réponses vérifiées.** Le programme doit rendre une ligne par ligne envoyée, chacune de cellules braille :
  toute autre chose arrête le lot, pour que rien d'autre que du braille n'atteigne un fichier.
- **Un liblouis sans défaut de sûreté de la mémoire connu.** liblouis 3.39.0 corrige des défauts de sûreté de la
  mémoire, et les textes que lit le programme viennent de documents que personne n'a vérifiés. Avant sa première
  traduction, le traducteur demande sa version au programme, `lou_translate --version`, avec le même délai qu'un lot et
  une réponse d'au plus 4 Kio, et refuse une version antérieure à 3.39.0 par une `OutdatedLiblouis`, et une réponse qui
  ne nomme aucune version par une `LiblouisFailed`. Rien n'est envoyé à un liblouis refusé. `$allowOutdatedLiblouis`
  laisse traduire un tel liblouis, avec un avertissement dans le journal PSR-3 donné comme `$logger` ; voir
  [Configuration](configuration.md#le-plus-ancien-liblouis).
- **Rien d'autre n'est gardé.** Entre deux lots, le traducteur ne garde que la version que le programme a donnée, la
  même pour chaque requête.

Les messages des exceptions nomment l'échec, jamais le texte ; la sortie d'erreur du programme peut le citer, et va dans
un journal auquel l'application fait confiance.

Les tables sont des données de liblouis sous sa licence, installées avec lui ; le paquet n'en embarque aucune.
