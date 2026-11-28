<!-- phil-dev source-sha256=7d831587ec3102ceffdf91a168e60095aaad9ed34739395563b458b3ab2ae2c7 -->
# Sécurité

## La protection des formules

Un tableur exécute comme une formule une cellule dont le texte commence par `=`, `+`, `-` ou `@`, et certains aussi
une cellule qui commence par une tabulation ou un retour chariot : un texte qu'un membre a saisi dans un sondage peut
alors calculer, aller chercher une adresse ou exécuter une commande à l'ouverture de l'exportation (OWASP, injection
CSV). Les écrivains gardent ces textes comme textes, sauf si `WriteOptions::$guardFormulas` est désactivé :

- **CSV** n'a pas de types : le texte reçoit une apostrophe devant, que les tableurs lisent comme « ceci est un
  texte » — `=1+1` s'écrit `'=1+1`. L'apostrophe fait partie du fichier : un programme qui relit le CSV la voit.
- **XLSX** écrit chaque texte comme une chaîne en ligne, qu'un tableur n'exécute jamais ; un texte qui s'exécuterait
  une fois modifié est marqué du préfixe d'apostrophe du format, qui le garde comme texte dans le tableur, et la valeur
  reste inchangée.
- **ODS** écrit chaque texte comme une cellule de chaîne, qu'un tableur n'exécute jamais : le format n'a rien à
  changer.

La ligne d'en-tête est protégée comme les données. Désactivez la protection seulement pour des textes que personne n'a
saisis — des codes que l'application a produits, par exemple.

## Lire des fichiers que personne n'a vérifiés

Les lecteurs sont faits pour les fichiers que les gens téléversent :

- **Tailles.** La taille du fichier est vérifiée avant que rien soit lu. Une archive qui a plus d'entrées que la limite
  est refusée à son ouverture. Chaque partie est lue à travers un budget d'octets qui les compte à mesure qu'ils sont
  décompressés : la taille qu'une archive déclare pour une entrée est vérifiée aussi, mais une bombe ZIP qui ment à
  son sujet est arrêtée par le compte.
- **Lignes, colonnes et cellules.** La ligne la plus haute, la ligne la plus large et les cellules de toute la lecture
  sont bornées, les lignes et cellules répétées d'un fichier ODS comprises : une ligne répétée un million de fois coûte
  un compteur, pas un million de lignes, tant qu'elle n'a pas de cellule.
- **Entrées hors de la racine.** Une archive dont une entrée a un nom absolu, utilise une barre oblique inverse ou une
  lettre de lecteur, contient un octet NUL ou un segment `..` est refusée à son ouverture ; une relation dont la cible
  sort au-dessus de la racine est refusée à sa lecture. Rien n'est jamais extrait sur le disque : les parties sont lues
  depuis l'archive.
- **XML.** Une partie qui déclare un type de document est refusée avant qu'aucune entité soit développée : aucune
  entité externe n'est chargée (XXE) et aucune entité ne se multiplie (*billion laughs*). L'analyseur n'atteint jamais
  le réseau. Une partie qui se termine avant la fermeture de son élément racine est refusée comme mal formée. Les
  avertissements de libxml ne sont pas remontés à l'appelant : une partie illisible est refusée par une `ReadRefused`.
- **Les parties chiffrées** sont refusées.
- **Textes.** Un enregistrement CSV qui n'est pas en UTF-8 est refusé.

Chaque refus est une `ReadRefused`, sous `SpreadsheetException` ; son message nomme la limite ou la partie, jamais le
contenu d'une cellule.

Les trois lecteurs ont des cibles de fuzzing : voir [Tests](testing.md).

## Écriture

- Un texte qui n'est pas en UTF-8 est refusé par une `WriteFailed` plutôt qu'écrit en un fichier brisé.
- Les écrivains XLSX et ODS écrivent dans des fichiers temporaires qu'ils suppriment, même quand l'écriture échoue ; le
  flux ne reçoit le fichier qu'une fois celui-ci complet.
