<!-- phil-dev source-sha256=ef76ed1325c2620ff67104044d45fd9152d30af02bbfe8bcaa0176cad82b93d7 -->
# Segments

## Le langage de requête

Un segment s'écrit dans la forme JSON versionnée de phil/rules, avec son nom à côté de sa condition :

```json
{
    "version": 1,
    "name": "Weekend buyers who are not crew",
    "condition": {
        "all": [
            {"type": "ticket.holder", "operands": {"event": "spring-fair-2027", "ticket_types": ["weekend"]}},
            {"not": {"type": "person.tagged", "operands": {"tags": ["crew"]}}}
        ]
    }
}
```

Le même texte est ce qu'une API accepte, ce qu'un éditeur visuel produit et ce que l'application stocke. `SegmentJson`
le lit strictement — toute autre clé, un type inconnu ou un opérande refusé est rejeté, comme phil/rules le décrit dans
sa forme JSON — et le réécrit de façon canonique. L'identifiant du segment appartient à l'application et est donné à la
lecture.

| Type | Opérandes | Vrai lorsque le membre |
|---|---|---|
| `ticket.holder` | `event`, `ticket_types` facultatif | détient un billet de l'événement, de l'un des types lorsqu'ils sont donnés |
| `date.participant` | `occurrence` | a participé à la date d'un événement |
| `selection.member` | `selection` | a été choisi par un organisateur dans la sélection |
| `purchase.made` | `skus`, `products`, `order_states` facultatifs | a une commande qui correspond à chaque partie donnée ; toute commande sans partie |
| `supporter.active` | `tiers` facultatif | soutient maintenant, à l'un des paliers lorsqu'ils sont donnés |
| `person.tagged` | `tags` | porte au moins l'une des étiquettes internes |

Chaque opérande est un identifiant ou une liste d'identifiants. Chaque évaluation reçoit un seul fait, `member`,
l'identifiant opaque du membre ; les types interrogent leur port sur ce membre. D'autres types de condition, les
comparaisons intégrées de phil/rules ou ceux de l'application, peuvent être ajoutés au registre.

## Segment dynamique ou liste figée

Un **segment** est dynamique : ses membres sont ceux qui correspondent à la règle maintenant. Interrogé demain, après
une vente de billets ou un désabonnement, il répond autrement. Il convient à une page qui liste une audience, à un
décompte affiché dans un éditeur, ou à un parcours qui démarre quand quelqu'un y entre.

Une **liste figée** est constituée des membres à un moment donné, conservés tels qu'ils étaient. Lorsqu'un message est
envoyé, l'application fige le segment et stocke la liste avec le message : c'est la preuve de qui devait le recevoir,
quoi que deviennent les faits. Elle ne contient que des identifiants opaques, en ordre croissant d'octets, la date de
son gel et l'empreinte de la règle qui les a sélectionnés ; deux listes de même empreinte proviennent de la même règle.

```php
$list = $segments->freeze($segment);
$message->recipients = $list;   // stored with the message, never recomputed

foreach ($list->page(after: null, limit: 500) as $member) { … }
$list->contains('person:42');
```

Un expéditeur vérifie de nouveau les exclusions à l'envoi de chaque lot, pour les membres qui se sont désabonnés après
le gel, et les consigne comme ignorés.

## Exclusions

Le port `Exclusions` répond lesquels d'une page de membres sont exclus : désabonnés, ayant refusé les messages, rebondis
de façon permanente. `Segments` l'interroge pour chaque page qu'il lit et les retire avant d'évaluer la règle, de sorte
qu'aucun segment ne puisse le contourner. `Segments::check()` évalue tout de même la règle pour un membre exclu, afin
que le soutien voie les deux raisons dans le `Membership` qu'il renvoie.

## Pages

`Segments::page($segment, $after, $limit)` lit l'audience à partir du curseur et renvoie jusqu'à `$limit` membres
(1 000 au plus) et le curseur de la page suivante, nul à la fin de l'audience. Un appel lit au plus `maxScanned` membres
de l'audience : une page peut revenir courte avec un curseur suivant lorsque peu de membres correspondent.

## Recalcul

`Recalculation::recalculate()` compare le segment tel qu'il est maintenant avec les membres qu'un `MembershipStore` a
conservés de la dernière exécution. Il parcourt côte à côte l'audience et le magasin, tous deux en ordre croissant
d'octets, puis écrit les changements par lots, publie un `MemberEntered` ou un `MemberLeft` pour chacun par le
répartiteur PSR-14, et consigne la date de l'exécution, que `MembershipStore::lastRecalculation()` redonne pour
l'affichage. Un membre sort lorsque la règle échoue pour lui, lorsqu'il est exclu, ou lorsqu'il n'est plus dans
l'audience.

Les événements portent l'identifiant du segment, l'identifiant du membre et la date : une application peut les
transmettre tels quels comme messages Messenger, sans adresse ni nom. Ils suivent les écritures, de sorte qu'une
exécution interrompue entre les deux les publie de nouveau à l'exécution suivante : un écouteur les traite comme
livrés au moins une fois.

Un calcul complet, une liste figée ou un recalcul lit au plus `maxMembers` membres de l'audience et lance
`SegmentTooLarge` au-delà, avant d'écrire quoi que ce soit.

## Ce qu'un segment refuse

- **Le profilage** : un trait deviné, un intérêt prédit, un score de comportement ou d'engagement. Décider au sujet
  d'une personne à partir d'une telle inférence est ce que la loi sur la vie privée demande de divulguer et de limiter ;
  les segments sont des règles explicites sur des faits que la personne a donnés ou accomplis.
- **L'ouverture et le clic** : qu'un message ait été ouvert ou qu'un lien ait été cliqué. L'ouverture se mesure par des
  pixels espions, que les outils de vie privée bloquent ou falsifient, et les clics par des liens réécrits ; ni l'un ni
  l'autre n'est un fait sur lequel sélectionner des gens.
- **Les plateformes externes de données clients** : les segments se calculent à partir des données propres de
  l'application, par ses ports.

`Segment` refuse toute règle dont les types de condition portent, comme mot de leur nom, l'un des
`Segment::FORBIDDEN_WORDS` : `open`, `opened`, `click`, `clicked`, `score`, `predicted`, `inferred`, `propensity`,
`lookalike`, `affinity` et leurs formes.

## Bornes

| Borne | Valeur par défaut | Où |
|---|---|---|
| `pageSize` | 500 | membres lus à la fois dans l'audience, de 1 à 1 000 |
| `maxScanned` | 10 000 | membres que lit un appel de `page()` |
| `maxMembers` | 200 000 | membres que lit un calcul complet |
| `batchSize` | 500 | membres lus dans le magasin, et écrits dans le magasin, à la fois |

La règle d'un segment est bornée par les `Limits` de phil/rules données à `SegmentJson`.
