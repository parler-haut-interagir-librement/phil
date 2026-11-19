<!-- phil-dev source-sha256=920e360d60ed8c8e6f661543f87ecb9b9baae4292e1b5b83d2deb4b10d70a549 -->
# Algorithmes

Chaque assigneur commence de la même façon : il tire du germe l'ordre des candidats, place les candidats forcés, puis
résout pour les autres. Quel que soit le mécanisme, un créneau ne contient jamais plus que sa capacité, un candidat est
placé au plus une fois et jamais dans un créneau interdit ou fermé, chaque candidat forcé est dans son créneau, et un
groupe est placé en entier ou pas du tout.

## Le tirage

Les candidats sont pris dans l'ordre des identifiants et mélangés par le `Random\Randomizer` de PHP sur le moteur
`Xoshiro256StarStar`, initialisé avec le SHA-256 du texte du germe. PHP fixe la séquence de ce moteur : le même germe
donne donc le même ordre sur toutes les machines et dans toutes les versions de PHP. L'ordre du tirage revient avec le
résultat.

## Rondes de choix classés

`RankedRounds` avec `Mechanism::Rounds` : la ronde 1 offre à chaque candidat en attente son premier choix, dans l'ordre
du tirage ; un candidat dont le choix a de la place est placé, les autres attendent. La ronde 2 offre aux candidats en
attente leur deuxième choix, et ainsi de suite, jusqu'à ce que personne n'attende ou que personne n'ait plus de choix
pour la ronde. Un choix qui était plein quand sa ronde est venue est perdu, même s'il était le premier du candidat.

- Le premier choix de chacun est considéré avant le deuxième de quiconque : le nombre de premiers choix accordés est
  aussi élevé que le tirage le permet.
- Avec `RoundOrder::Alternating`, les rondes paires prennent les candidats en attente dans l'ordre inverse du tirage :
  le candidat servi en dernier à la ronde 1 est servi en premier à la ronde 2.
- Un candidat peut gagner à classer en premier un créneau moins populaire que celui qu'il veut le plus, puisqu'un
  premier choix disputé peut lui coûter son deuxième. Les organisateurs qui annoncent des rondes l'acceptent
  généralement ; la dictature en série n'a pas ce défaut.

Chaque placement porte sa ronde et son rang, qui sont égaux dans ce mécanisme.

## Dictature en série

`RankedRounds` avec `Mechanism::SerialDictatorship` : une seule passe dans l'ordre du tirage, chaque candidat prenant
son meilleur choix qui a encore de la place. Chaque placement porte la ronde 1 et le rang obtenu.

- Aucun candidat ne gagne à mal déclarer son classement : les choix au-dessus de celui qu'il a obtenu étaient pleins
  quand son tour est venu.
- Le résultat est efficace au sens de Pareto pour le tirage : deux candidats ne peuvent pas échanger leur place et y
  gagner tous les deux.
- Le tirage compte plus que dans les rondes : les premiers tirés obtiennent toujours leur premier choix, les derniers
  prennent ce qui reste.

## Optimisation globale

`MinCostAssigner` résout pour tout le monde à la fois :

1. Les groupes, dans l'ordre du tirage, prennent chacun leur meilleur choix qui a de la place pour tous ses membres, et
   sont placés comme `PlacedBy::Group`.
2. Les gens seuls forment un réseau de flot : une arête d'une source vers chaque personne, une arête de chaque personne
   vers chaque créneau qu'elle a classé et où elle peut entrer, au prix du coût de rang de ce choix, et une arête de
   chaque créneau vers un puits avec les places restantes. Un flot maximal de coût minimal sur ce réseau est
   l'affectation.
3. Le flot est résolu par la méthode primale-duale : l'algorithme de Dijkstra sur des coûts réduits par des potentiels
   de nœuds établit le prix du réseau, puis des recherches en profondeur poussent du flot le long de chaque chemin de
   coût réduit nul, et le réseau est de nouveau tarifé jusqu'à ce que le puits ne soit plus atteignable. Un départ à
   chaud assoit d'abord chaque personne, dans l'ordre du tirage, à un choix du coût le plus bas tant qu'il y en a un qui
   a de la place.

Le résultat place autant de gens seuls que n'importe quelle affectation des places restantes le pourrait et, parmi les
affectations qui en placent autant, son coût de rang total est le plus bas. Entre des affectations de coût égal, l'ordre
du tirage tranche, par l'ordre des nœuds du réseau. La suite de tests vérifie les deux propriétés contre une recherche
exhaustive sur 150 problèmes aléatoires, pour les deux coûts de rang.

Le coût de rang dit ce que signifie l'optimum :

| Coût | Rang 1, 2, 3, 4 | L'optimisation |
|---|---|---|
| `LinearRankCost` | 0, 1, 2, 3 | rend la somme des rangs aussi petite que possible ; peut donner à une personne son quatrième choix pour donner à trois autres leur premier |
| `SquaredRankCost` | 0, 1, 4, 9 | fait peser un mauvais placement plus que plusieurs petits, et répartit la déception |

Les groupes sont placés d'abord parce qu'un groupe doit rester entier : un flot peut scinder les places d'un groupe
entre des créneaux, et placer des groupes entiers de façon optimale est un problème de remplissage sans méthode exacte
efficace. Les placer d'abord, dans l'ordre du tirage, garde le résultat exact pour tous les autres et explique le
placement de chaque groupe.

## Minimums

Après une résolution, un créneau ouvert qui contient quelques personnes mais moins que son minimum est fermé : celui qui
a le moins de gens d'abord, et entre égaux celui qui a le premier identifiant. Tout le problème est alors résolu de
nouveau sans lui, à partir du même tirage, de sorte que ses gens vont à leurs choix suivants. Cela se répète jusqu'à ce
qu'aucun créneau ouvert ne soit sous son minimum.

- Un créneau vide n'a pas lieu et n'est jamais fermé.
- Un créneau qui contient un candidat forcé n'est jamais fermé : l'organisateur l'a forcé.
- La fermeture d'un créneau peut en pousser un autre sous son minimum ; les fermetures se poursuivent, une à la fois,
  de sorte que chacune soit expliquée par la résolution qui la précède.

Les créneaux fermés reviennent dans `closedSlots`, dans l'ordre où ils ont été fermés, et le choix d'un candidat non
placé dans un créneau fermé se lit `ChoiceOutcome::Closed`.

## Complexité et budgets

| Assigneur | Une résolution | Avec minimums |
|---|---|---|
| `RankedRounds` | O(A · C) pour A candidats qui classent jusqu'à C créneaux | jusqu'à S + 1 résolutions pour S créneaux |
| `MinCostAssigner` | O(F · E log V) au pire, F les gens seuls, E leurs choix plus F plus S, V les candidats plus S ; bien moins de tarifications quand les rangs sont peu nombreux, puisqu'une tarification sert tous les chemins de même coût | jusqu'à S + 1 résolutions |

Mesuré sur un portable avec PHP 8.5, mille personnes qui classent cinq créneaux parmi cent de dix places : quelques
millisecondes pour les rondes et pour la dictature en série, et environ un dixième de seconde de plus quand les
minimums ferment des créneaux ; entre 0,2 et 0,5 s pour l'optimisation, et une à deux secondes quand les minimums
ferment quelques créneaux et que le problème est résolu de nouveau.

Deux bornes gardent une exécution à la portée d'une requête :

- `Limits` refuse un problème au-delà de 5 000 créneaux, 20 000 candidats, 20 choix par candidat ou 50 membres par
  groupe avant tout travail. Une application les abaisse pour une entrée en laquelle elle n'a pas confiance.
- `WorkBudget` compte les étapes élémentaires — un choix essayé, une arête relâchée, une étape d'une recherche — sur
  toute l'exécution, fermetures comprises, et lance `BudgetExceeded` au-delà de 50 millions par défaut. Un problème
  dans les limites mais hostile par sa forme s'arrête là au lieu de retenir un worker. Une application qui affecte hors
  ligne le relève.
