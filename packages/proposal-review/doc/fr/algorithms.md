<!-- phil-dev source-sha256=aff680544d8db29c7c81ca88dc1a51d65f08a8ad360431ebf1ae6ed8d663e6da -->
# Algorithmes

## Les notes et leur agrégation

Une évaluation donne à chaque critère une note sur l'échelle de ce critère. `Scoring::review()` ramène chaque note à
0–1 — `(score − min) / (max − min)` — pour qu'un critère sur 10 ne pèse pas plus qu'un critère sur 5, puis calcule la
moyenne pondérée : la note de l'évaluation, de 0 à 1. La note d'une proposition agrège les notes de ses évaluations :

- `Aggregation::Mean`, la moyenne : chaque évaluation compte autant.
- `Aggregation::Median`, la valeur du milieu, ou la moyenne des deux valeurs du milieu : un seul évaluateur sévère ou
  généreux la déplace peu.

Une proposition sans évaluation n'a pas de note, et `enoughReviews` dit si elle a le minimum de la politique. `Scoring`
est une classe pure : une application, ou un paquet d'évaluation par les pairs, peut noter et agréger avec elle sans le
tableau d'évaluation.

## Assignation équilibrée

`assignBalanced($campaign, $reviewers, $perProposal)` donne à chaque proposition jusqu'à `$perProposal` évaluateurs, en
comptant ceux qu'elle a déjà :

1. Les propositions sont prises la moins assignée d'abord, puis par identifiant.
2. Pour chacune, les candidats sont les évaluateurs donnés, pas encore assignés à elle et pas en conflit avec elle,
   classés selon le nombre de propositions auxquelles ils sont assignés dans la campagne, puis par identifiant.
3. Les premiers candidats sont assignés, et leur charge grandit aussitôt, avant la proposition suivante.

Le résultat ne dépend que des propositions, des évaluateurs et des assignations déjà faites : l'exécuter deux fois ne
change rien la seconde fois. Il répartit la charge de façon gloutonne, toujours vers l'évaluateur le moins chargé qui
peut prendre la proposition, mais ne cherche pas le partage parfaitement égal quand des conflits font d'un évaluateur
le seul choix pour plusieurs propositions. Il coûte O(P · R log R) pour P propositions et R évaluateurs, plus la
vérification des conflits.

## Enregistrer et suivant

`nextFor($reviewer, $campaign)` renvoie, parmi les propositions que l'évaluateur peut évaluer et n'a pas évaluées,
celle qui a le moins d'évaluations, et entre égales le premier identifiant. Des évaluateurs qui avancent ainsi amènent
chaque proposition au minimum avant qu'une proposition reçoive beaucoup d'évaluations.

## Le tirage des paires

`PairwiseVoting::nextPair($voter, $campaign)` :

1. Les propositions admissibles sont celles avec lesquelles le votant n'est pas en conflit.
2. L'exposition de chacune est le nombre de votes, par n'importe qui, auxquels elle a participé.
3. Elles sont classées par exposition, puis par le SHA-256 de la campagne, du votant, du nombre de votes que le votant a
   exprimés et de la proposition — un départage qui diffère d'un votant à l'autre et d'un vote à l'autre, de sorte que
   deux votants ne voient pas la même paire en premier, mais qu'on peut le recalculer à partir du journal —, puis par
   identifiant.
4. La première proposition de cet ordre est jumelée à la première qui la suit dans le même ordre et que le votant n'a
   pas comparée avec elle ; si le votant l'a comparée avec toutes les autres, on essaie la proposition suivante. Plus
   aucune paire : null.

Chaque votant voit chaque paire au plus une fois, et les propositions les moins vues sont montrées d'abord, de sorte que
l'exposition reste égale sur toute la campagne. Le coût est O(V + P log P) pour lire le journal et classer les
propositions, et O(P²) au pire, quand le votant a jugé presque toutes les paires.

## Les notes par paires

Le modèle donne à chaque proposition i une force p(i), et la probabilité que i soit préférée à j est
p(i) / (p(i) + p(j)). `BradleyTerry::fit()` trouve les forces qui rendent les votes journalisés les plus probables, avec
un a priori de `a` victoires et `a` défaites pour chaque proposition contre une référence virtuelle de force 1. L'a
priori garde une proposition invaincue à une force finie, tire vers la référence les propositions qui ont peu de votes,
et fixe l'échelle, que les votes seuls laissent libre.

Chaque itération a deux étapes, dont chacune ne peut que rendre les votes plus probables :

1. La mise à jour MM de Hunter (2004), qui remonte à Zermelo, pour toutes les propositions ensemble à partir des forces
   précédentes : p(i) ← (W(i) + a) / ( Σ_j n(i, j) / (p(i) + p(j)) + 2a / (p(i) + 1) ), où W(i) compte les victoires de
   i et n(i, j) les votes entre i et j.
2. Un facteur commun c, qui multiplie chaque force, choisi comme la racine de Σ (1 − c·p(i)) / (1 + c·p(i)), trouvée par
   soixante pas de dichotomie sur log c. Les votes ne voient pas l'échelle ; sans cette étape, l'itération s'y
   traînerait pendant des milliers de pas.

L'itération s'arrête quand aucune force logarithmique ne bouge de plus que la tolérance, ou après les itérations permises
(500 par défaut, 10 000 au plus). Avec l'a priori, la vraisemblance a un seul maximum, que l'itération atteint : pour
deux cents propositions et vingt mille votes, à 10⁻⁴ près en quarante itérations et jusqu'au dernier chiffre en
quatre-vingts.

Garanties :

- Reproductible : la mise à jour ne lit que les décomptes de victoires et de votes par paire, jamais l'ordre du
  journal, et met à jour chaque proposition à partir de la même étape précédente. Le même journal donne les mêmes notes.
- Les votes sur des propositions qui ne sont plus dans la campagne sont écartés.
- Une proposition sans vote garde la force 1, soit une force logarithmique de 0.

Chaque itération coûte O(P + D) pour P propositions et D paires distinctes votées, plus 60 · P pour l'échelle. Mesuré
sur un portable avec PHP 8.5 : environ un tiers de seconde pour deux cents propositions et vingt mille votes.
