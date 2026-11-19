<!-- phil-dev source-sha256=1eb16a9b143f04bbae0583d07d1b22b57cd0f7378ab260fc08f62c9713c5158e -->
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
- **Rien n'est gardé.** Le traducteur ne garde aucun état entre deux lots.

Les messages des exceptions nomment l'échec, jamais le texte ; la sortie d'erreur du programme peut le citer, et va dans
un journal auquel l'application fait confiance.

Les tables sont des données de liblouis sous sa licence, installées avec lui ; le paquet n'en embarque aucune.
