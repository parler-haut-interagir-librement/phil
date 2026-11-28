<!-- phil-dev source-sha256=aeb38441c4e62b611b0fa475f0cd84a7498e2a86514d563442c153471c18186e -->
# La liste de contrôle

## Périodes, avantages et droits

- **`Period`** nomme une période comme l'application les compte : `2026-11` pour un mois, `2026-Q4` pour un trimestre,
  `2026` pour une année. Les périodes se comparent par leur nom seulement.
- **`Benefit`** est ce qui est dû à chaque période : un code (`postcard`), un libellé qu'une personne lit, et s'il
  **expédie un objet**. Le code est écrit dans l'export de livraison ; il nomme donc l'objet, jamais le palier.
- **`Entitlement`** est ce qui est dû à un membre pour une période : son identifiant et les avantages de son palier. Le
  palier reste à la source.

Un membre est nommé par l'identifiant stable que l'application lui donne, jamais par une adresse courriel, qui
changerait et se retrouverait dans les fichiers : un identifiant qui contient `@` est refusé.

## D'où viennent les droits

L'application implémente `Contract\EntitlementSource` sur le modèle de ses paliers : abonnements, adhésions, dons. Elle
répond à deux questions :

- `entitlements($period, $after, $limit)` : une page de membres à qui quelque chose est dû pour la période, ordonnés par
  membre, après le membre `$after` ;
- `entitlementsOf($period, $members)` : les droits des membres nommés, ceux à qui quelque chose est dû seulement.

Un membre dont l'abonnement a pris fin, ou dont le don ne couvre pas la période, est écarté par la source : la liste de
contrôle ne lui doit rien.

## Les lignes

```php
$page = $checklist->page($period);                   // every line of the first 100 members
$page = $checklist->page($period, 'postcard');       // the postcards only
$page = $checklist->remaining($period, 'postcard');  // the postcards not delivered yet
$next = $checklist->remaining($period, 'postcard', $page->next);
```

Une page couvre jusqu'à `$limit` membres, de 1 à 1000, et donne un `ChecklistItem` par avantage dû à chaque membre : la
`DeliverableKey` (période, avantage, membre), le `Benefit`, et la `Delivery` une fois marquée. `$page->next` est le
membre après lequel la page suivante commence, ou `null` à la dernière page. Chaque page lit la source une fois et le
magasin une fois ; une période de n'importe quelle taille se lit donc en mémoire bornée.

## Marquer fait

```php
$checklist->markDone($period, 'postcard', ['member-1', 'member-2'], $now, 'staff-7');  // by lot, up to 1000
$checklist->markDone($period, 'credits', ['member-3'], $now, 'staff-7');               // one by one
$checklist->unmark($period, 'postcard', 'member-2');                                   // marked by mistake
```

- Chaque `Delivery` enregistre quand et par qui dans l'équipe.
- Un membre à qui l'avantage n'est pas dû pour la période est écarté ; `markDone()` retourne combien ont été marqués.
- Un élément déjà livré garde sa première livraison : le marquer de nouveau ne change rien, de sorte qu'un lot peut être
  envoyé deux fois.

## Où les livraisons sont gardées

L'application implémente `Contract\DeliverableStore` : `deliveries()` lit celles d'une page de membres, `markDone()`
écrit un lot et garde la première livraison de chaque élément, `unmark()` en oublie une. Une table dont la clé est la
période, l'avantage et le membre suffit.
