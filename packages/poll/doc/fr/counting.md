<!-- phil-dev source-sha256=dcd826b4c57f34438d8a24fa1cd916d3e0b4d65762014bb9685668733585d077 -->
# Dépouillement

Une `CountingMethod` lit les bulletins une seule fois, dans leur ordre, et retourne un `Result` :

| Propriété | Ce qu'elle contient |
|---|---|
| `method` | Le nom de la méthode. |
| `ballots` | Les bulletins dépouillés, abstentions comprises. |
| `abstentions` | Les bulletins blancs. |
| `standings` | Un `Standing` par option — `option`, `score`, `place` — de la première place à la dernière. |
| `rounds` | Les tours d'un vote alternatif ; vide pour les autres méthodes. |
| `rating` | Pour une échelle, un `Rating` : `marked`, les bulletins qui ont donné une note, `sum`, la somme de leurs notes, et `mean()` ; `null` pour les autres sortes. |
| `netPromoter` | Pour un Net Promoter Score, un `NetPromoter` : `promoters`, `passives`, `detractors`, `respondents()` et `score()` ; `null` sinon. |

Les options à égalité partagent leur place, et la place suivante compte toutes les options qui la précèdent : deux
options à égalité au premier rang sont toutes deux premières, et la suivante est troisième. Au sein d'une place, le
score le plus élevé vient d'abord, puis l'ordre déclaré. `winners()` énumère les options en première place, plusieurs
quand elles sont à égalité, et aucune tant qu'aucun bulletin n'a rien choisi. `standingOf()` trouve le classement
d'une option.

## Le compte des choix

`ChoiceCount`, nommé `choices`, dépouille les bulletins uniques et multiples : le score d'une option est le nombre de
bulletins qui l'ont choisie. Pour des bulletins uniques, c'est une majorité simple ; pour des bulletins multiples, un
vote par approbation. Il refuse les bulletins classés et les échelles.

## Le vote alternatif

`InstantRunoff`, nommé `instant_runoff`, dépouille les bulletins classés par tours. Chaque bulletin va à l'option la
mieux classée encore en lice. Une option qui détient plus de la moitié des bulletins encore comptés gagne ; sinon,
l'option qui en détient le moins est éliminée et ses bulletins vont à leur choix suivant. Un bulletin qui ne classe
aucune option restante est épuisé, et ne compte plus pour la majorité.

Une égalité pour le moins de bulletins se brise par les tours précédents, du plus récent au plus ancien : l'option à
égalité qui détenait le moins de bulletins au dernier tour où elles différaient est éliminée. Quand elles n'ont jamais
différé, celle qui a été déclarée en dernier est éliminée. Une application qui préfère un tirage au sort déclare
plutôt les options dans un ordre aléatoire à la création du scrutin. Quand toutes les options encore en lice sont à
égalité, le dépouillement s'arrête et elles partagent la première place.

Le classement met le gagnant en premier, puis les options encore en lice selon leurs bulletins au dernier tour, puis
les options éliminées, la dernière éliminée d'abord. Le score d'une option est le nombre de bulletins qu'elle détenait
au dernier tour où elle était en lice. Chaque `Round` contient un `Tally` par option encore en lice, les bulletins
épuisés et les options éliminées à la fin du tour.

## La méthode Borda

`Borda`, nommé `borda`, dépouille les bulletins classés par points : avec n options, le premier choix d'un bulletin
gagne n − 1 points, son deuxième n − 2, et ainsi de suite ; une option qu'un bulletin ne classe pas n'en reçoit rien.
Le score est le total des points.

## La méthode Schulze

`Schulze`, nommé `schulze`, est une méthode Condorcet pour les bulletins classés. Chaque paire d'options est comparée
sur chaque bulletin : un bulletin préfère une option qu'il classe à une autre qu'il classe plus bas ou pas du tout, et
ne préfère aucune de deux options qu'il ne classe pas. La force d'un chemin entre deux options est sa plus faible
victoire par paire, comptée en votes gagnants, et une option bat une autre quand son chemin le plus fort vers elle est
plus fort que le chemin inverse.

La place d'une option compte les options qui la battent : les options qu'aucune autre ne bat partagent la première
place, et une option qui bat toutes les autres — le gagnant de Condorcet, quand il y en a un — gagne seule. Le score
est le nombre d'options qu'une option bat. Deux options dont aucune ne bat l'autre peuvent se trouver à des places
différentes, quand d'autres options battent l'une et pas l'autre.

## Le dépouillement d'une échelle

`ScaleCount`, nommé `scale`, dépouille les notes d'une échelle et en est la méthode par défaut. Le score d'une note est
le nombre de bulletins qui l'ont donnée : la distribution des notes, la note donnée le plus souvent en premier, que
`winners()` nomme. Le `Rating` du résultat contient les bulletins qui ont donné une note et la somme de leurs notes,
deux entiers exacts ; `mean()` est leur quotient, `null` tant qu'aucun bulletin n'a donné de note. Les abstentions
sont comptées à part et laissées hors de la moyenne.

## Le Net Promoter Score

`NetPromoterScore`, nommé `nps`, dépouille seulement une échelle de 0 à 10, telle que `PollRules::netPromoter()` la
construit, et refuse toute autre. Ses classements et sa cote sont ceux du dépouillement d'une échelle ; son
`NetPromoter` répartit les notes en trois groupes : les promoteurs ont donné 9 ou 10, les passifs 7 ou 8, les
détracteurs de 0 à 6. `score()` est la part des promoteurs moins la part des détracteurs, en points de -100 à 100 —
25.0 pour quatre promoteurs, deux passifs et deux détracteurs —, `null` tant qu'aucun bulletin n'a donné de note. Les
groupes sont des entiers exacts ; arrondir le score revient à l'affichage.

## Coût

| Méthode | Temps | Mémoire |
|---|---|---|
| `choices`, `borda`, `scale`, `nps` | une passe, proportionnelle aux choix | les scores |
| `instant_runoff` | une passe pour regrouper, puis une passe sur les classements distincts à chaque tour | les classements distincts |
| `schulze` | une passe pour regrouper, les paires de chaque classement distinct, puis le cube du nombre d'options | les classements distincts et deux tableaux carrés |

Avec dix options, chaque méthode dépouille dix mille bulletins en une dizaine de millisecondes sur un portable ; voyez
[Tests](testing.md#le-banc-dessai).
