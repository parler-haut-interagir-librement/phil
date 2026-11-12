<!-- phil-dev source-sha256=be8f84295deae8fa50079b6bcfa57d3465f4474174f1e59f408ba59c23d58247 -->
# Comment il indexe

Chaque index de `phil/search-bundle` est un index Loupe à lui, dans un répertoire à son nom sous le répertoire
configuré, ou en mémoire sans répertoire. Ce répertoire contient `phil_search.json`, un fichier de verrou, et le
répertoire des fichiers de Loupe que nomme `phil_search.json` : `loupe`, puis `loupe-` suivi de douze chiffres
hexadécimaux après une reconstruction ou un nouveau champ.

- **Seuls les champs texte sont cherchés**, et un document est trouvé quand il contient tous les mots cherchés, comme
  avec l'adaptateur `dbal` du bundle ; Loupe classe les documents trouvés, tolérance aux fautes de frappe comprise.
- **Les filtres sont gardés comme listes de texte** : Loupe donne un seul type à chaque champ, et un filtre peut être
  une valeur ou une liste. Une liste correspond quand l'une de ses valeurs correspond ; une valeur est comparée comme
  du texte. Un filtre d'une requête qui est une liste trouve un document qui a l'une de ses valeurs. Un filtre sur un
  champ qu'aucun document n'a ne trouve rien.
- **Les facettes sont la distribution de facettes de Loupe**, sur les champs filtrés : une facette dont le propre
  filtre est défini est comptée par une recherche de plus, sans ce filtre et sans résultats, pour que ses autres
  valeurs gardent leur compte. Une facette sur un champ qu'aucun document n'a comme filtre ne donne aucune valeur.
- **Les synonymes ne sont pas appliqués** : Loupe ne lit ni les variantes que `phil/search-bundle` donne aux termes
  d'une requête, ni une liste à lui ; les mots sont cherchés tels quels.
- **Les champs sont appris à mesure que les documents arrivent.** Loupe déclare les champs cherchés et filtrés d'un
  index avant d'indexer : l'adaptateur les garde dans `phil_search.json`, à côté de l'index, et les élargit quand un
  document en apporte un nouveau. L'index est alors rempli de nouveau avec les documents qu'il contient — un moment
  plus long, une seule fois, environ une milliseconde par document. Sur disque, ils sont copiés une page à la fois dans
  un nouveau répertoire de fichiers de Loupe, sous le verrou ; `phil_search.json` nomme ensuite ce répertoire, et
  l'ancien est supprimé, comme le fait une reconstruction. Loupe n'est jamais laissé migrer ses propres fichiers :
  Loupe 1.1.6 rate cette migration dès qu'un index contient une centaine de documents ou plus.
- **Les champs cherchés gardent leur ordre d'arrivée**, selon lequel Loupe classe : avec un fournisseur qui donne
  `title` avant `body`, une correspondance dans le titre passe devant une correspondance dans le corps. Un champ appris
  plus tard vient après les autres.
- **Chaque processus voit les mêmes champs.** Le fichier est vérifié avant chaque utilisation : un worker de longue
  durée voit ainsi un champ qu'une requête Web a ajouté, ou une reconstruction qu'un autre processus a faite. Il est
  gardé en mémoire et relu seulement quand son inode, sa taille ou sa date de modification a changé — chaque écriture
  le remplace par un renommage —, ou quand il a changé pendant la seconde en cours, ce qu'une date de modification ne
  permet pas de distinguer. Il est élargi sous un verrou et relu à l'intérieur, pour qu'aucun processus ne réécrive
  moins de champs qu'un autre n'en a appris.
- **Une écriture n'aboutit jamais dans un répertoire en cours de copie.** L'ajout, le retrait et le vidage de
  documents tiennent le même verrou, partagé : les écritures s'exécutent côte à côte, et attendent pendant qu'un
  processus élargit les champs ou bascule vers un répertoire reconstruit.
- **Une reconstruction garde l'index interrogeable.** `rebuild()` remplit un nouveau répertoire de fichiers de Loupe à
  côté de celui en service, puis le nomme dans `phil_search.json` et supprime l'ancien ; une reconstruction qui échoue
  supprime son propre répertoire et laisse l'index tel qu'il était. En mémoire, l'index est remplacé une fois chaque
  document lu.
- Un nom d'index invalide — autre que 1 à 64 lettres minuscules, chiffres ou `_` — est refusé avant qu'aucun chemin ne
  soit construit.
- Une page, c'est `limit` et `offset`, au plus 1000 documents, et le total est le compte que fait Loupe des documents
  trouvés.
