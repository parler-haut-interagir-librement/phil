<!-- phil-dev source-sha256=b369ca3e0c0318314812089b4aeec0013dad9b6b9b79ca5736b41b32a6f4e3f3 -->
# Sécurité

Une évaluation par jury décide qui parle, qui est admis, qui gagne. Le moteur fait respecter les règles qui la gardent
équitable ; l'application fait respecter qui est qui.

## Ce que le moteur fait respecter

- **Les phases comme permissions.** Chaque écriture vérifie la phase de la campagne selon l'horloge : les propositions
  pendant le dépôt, les notes et les votes pendant l'évaluation, les décisions pendant la délibération, les conflits et
  les assignations avant la délibération. Une requête qui arrive en retard est refusée par `Refused`, quoi que l'écran
  ait montré.
- **Les conflits d'intérêts.** Un évaluateur est en conflit avec ses propres propositions, et avec toute proposition que
  lui ou les organisateurs ont déclarée — une proposition, ou toutes les propositions d'un auteur. En conflit, il n'est
  jamais assigné, n'évalue jamais, ne vote jamais sur une paire qui contient la proposition, et ne voit ni ses
  évaluations ni ses auteurs.
- **L'aveugle.** Dans une campagne à l'aveugle, `authorsVisibleTo()` ne donne aucun auteur tant que la décision de la
  proposition n'est pas appliquée, et `reviewsVisibleTo()` suit la politique, de sorte que les premières évaluations
  n'influencent pas les suivantes.
- **Les décisions définitives.** Les décisions restent en attente jusqu'à ce qu'on les applique ensemble ; une décision
  appliquée n'est pas remplacée.
- **Une entrée bornée.** Les identifiants font de 1 à 255 octets sans caractère de contrôle ; une campagne a au plus 50
  critères, une proposition au plus 50 auteurs, une politique au plus 1 000 évaluations ; les poids et les échelles sont
  bornés ; le modèle de Bradley–Terry itère au plus 10 000 fois.
- **Les lignes stockées.** Les magasins DBAL lient chaque valeur comme paramètre ; le préfixe de table, le seul nom qu'ils
  mettent dans le SQL, est d'abord vérifié contre un motif strict. Le JSON est relu à une profondeur de 8 au plus, et
  une ligne qu'ils n'ont pas écrite est refusée, jamais lue comme autre chose.

## Ce que l'application fait respecter

- **Qui est l'appelant.** Le moteur se fie aux identifiants d'évaluateur, d'auteur, de votant et de membre qu'on lui
  donne. L'application passe l'identifiant de l'utilisateur authentifié, jamais un identifiant lu dans la requête, et
  vérifie qu'un évaluateur appartient au jury — par exemple avec un voter Symfony — avant d'appeler le tableau
  d'évaluation.
- **Ce que le contenu révèle.** L'aveugle cache les identifiants des auteurs ; les noms dans le titre, le texte ou les
  fichiers d'une proposition sont à l'application de les caviarder.
- **À quelle fréquence on vote.** Un vote public par paires demande les propres limites de l'application, comme le
  limiteur de débit de Symfony, et ses propres vérifications contre les comptes en double.

## Pseudonyme par conception

Les évaluateurs, les auteurs, les votants et les membres sont désignés par les identifiants opaques et stables de
l'application. Un identifiant qui ressemble à une adresse de courriel est refusé : une adresse change et nomme une
personne. Les évaluations, les décisions et les votes ne contiennent que des identifiants et des numéros, et les
événements ne portent que des identifiants, de sorte que les journaux et les files ne contiennent aucun renseignement
personnel.

## Des résultats vérifiables

Le classement est calculé à partir des évaluations stockées, et les notes par paires à partir de tout le journal des
votes, chaque fois, sans état caché : quiconque a les évaluations ou le journal calcule le même classement. Le tirage
des paires utilise une empreinte de valeurs que le journal contient, de sorte que l'ordre des paires montrées peut lui
aussi être vérifié.
