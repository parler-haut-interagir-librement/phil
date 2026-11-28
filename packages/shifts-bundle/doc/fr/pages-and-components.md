<!-- phil-dev source-sha256=eead33d0fd96f77167b9aaea9afdafb87fba15e15b12455dfb57782955f36cdc -->
# Pages et composants

Chaque page est un simple formulaire Twig qui fonctionne sans JavaScript, d'abord au clavier, avec les classes du kit
PHIL UX — `isStack`, `isBadge`, `isWarning`, `isError`, `isSecondary`, `isDanger`. Chaque bouton envoie un jeton CSRF ;
un formulaire venu d'ailleurs est refusé par un 403. La raison d'un refus s'écrit dans un paragraphe `role="alert"`,
qu'un lecteur d'écran annonce, et la page répond 422, ou 404 pour un quart ou une offre inconnus. Les instants sont
montrés dans le fuseau horaire configuré, avec un `<time datetime>` en UTC.

## Les pages des bénévoles

| Route | Chemin | Montre |
|---|---|---|
| `phil_shifts_board` | `GET /` | Les quarts à venir, ceux qui sont critiques et manquent de bénévoles en premier, et la situation du bénévole. |
| `phil_shifts_show` | `GET /shift/{id}` | Un quart, ses places et sa réserve, et le bouton que donne son mode : s'inscrire, rejoindre la réserve, se dire intéressé, se désister, offrir sa place. |
| `phil_shifts_mine` | `GET /mine` | Les quarts du bénévole et les heures qu'il a servies, simples et pondérées. |
| `phil_shifts_swaps` | `GET /swaps` | Les places offertes sur des quarts encore ouverts, avec le bouton pour en prendre une, ou pour annuler la sienne. |

Les boutons envoient à `phil_shifts_sign_up`, `phil_shifts_interest`, `phil_shifts_withdraw`, `phil_shifts_swap_offer`,
`phil_shifts_swap_take` et `phil_shifts_swap_cancel`, et redirigent par un 303 vers la page d'où ils viennent. Un quart
commencé — ou dont la fenêtre d'une tâche ou d'une durée est terminée — ne montre aucun bouton.

Une inscription, un intérêt ou un échange qui donnerait à un bénévole un quart fixe chevauchant un quart qu'il occupe, ou
plus proche que `rest_minutes`, est refusé avec `shift.conflict`. Une place dans la réserve n'occupe aucun quart : elle
n'entre pas en conflit.

## Les pages des coordonnateurs

| Route | Chemin | Montre |
|---|---|---|
| `phil_shifts_manage` | `GET /manage` | Chaque quart à venir, ceux qui manquent de bénévoles signalés, et les échanges qui attendent une approbation. |
| `phil_shifts_manage_create` | `GET, POST /manage/new` | Le formulaire d'un nouveau quart. |
| `phil_shifts_manage_edit` | `GET, POST /manage/{id}/edit` | Le formulaire d'un quart, son identifiant fixe ; une capacité inférieure à ses bénévoles est refusée. |
| `phil_shifts_manage_roster` | `GET /manage/{id}` | Les places, la réserve et les intéressés, avec les boutons pour affecter, retirer, attribuer et supprimer ; une fois le quart commencé, pour enregistrer une absence non annoncée ou la reprendre ; pour un bénévole bloqué par ses absences, pour lever le blocage. |

Les boutons envoient à `phil_shifts_manage_assign` — un bénévole par son identifiant, ou un bénévole de la réserve ou des
intéressés —, `phil_shifts_manage_remove`, `phil_shifts_manage_allocate`, `phil_shifts_manage_delete`,
`phil_shifts_manage_absent`, `phil_shifts_manage_forgive`, `phil_shifts_manage_lift`, `phil_shifts_manage_approve` et
`phil_shifts_manage_refuse`. Un bénévole enregistré absent porte l'insigne « Absent sans prévenir », et celui qui a
dépassé le seuil « Bloqué par ses absences » : voir [Mineurs et absences](minors-and-absences.md).

L'attribution répartit `AllocateInterest` sur le bus Messenger de l'application quand il y en a un : traité tout de
suite, sauf si l'application l'achemine vers un transport, où un *worker* le traite. Sans Messenger, elle s'exécute
pendant la requête.

## Le formulaire d'un quart

`ShiftType` porte les champs d'un quart : identifiant, titre, type, début et fin dans le fuseau horaire configuré,
minutes pour une tâche ou une durée, places, réserve, mode, rôle exigé, critique, pondération, approbation des échanges,
lieu et description. `ShiftType::shift()` construit le quart à partir d'un formulaire soumis, ou ajoute au formulaire le
refus de la bibliothèque — son message est une clé du domaine `phil_shifts`, traduite en anglais et en français. Une
application l'utilise dans ses propres pages :

```php
use Phil\ShiftsBundle\Infrastructure\Form\ShiftType;

$form = $this->createForm(ShiftType::class, ShiftType::data($roster->shift), ['existing' => true]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid() && null !== $shift = ShiftType::shift($form, $roster->shift->id)) {
    $rosters->correct($shift);
}
```

## Les composants

Avec Symfony UX Twig Component installé, deux composants montrent les quarts sur n'importe quelle page :

```twig
{# A shift with the button of the person signed in; nothing for a shift unknown. #}
<twig:PhilShifts:ShiftCard id="desk-sat-am" />
<twig:PhilShifts:ShiftCard :roster="roster" :actions="false" class="isCard" />

{# The shifts to come of the person signed in, and the hours they served; nothing when nobody is signed in. #}
<twig:PhilShifts:MyShifts limit="5" />
```

| Composant | Propriétés | Produit |
|---|---|---|
| `PhilShifts:ShiftCard` | `id`, ou `roster` ; `actions`, `true` par défaut | un `<article class="phil-shifts-card">` avec le titre, la fenêtre, les places restantes et le bouton |
| `PhilShifts:MyShifts` | `limit`, de 1 à 50, 5 par défaut | une `<section class="phil-shifts-mine">` avec les quarts pas encore terminés et les heures servies |

Les autres attributs vont sur l'élément racine. Les boutons d'une carte envoient aux routes des bénévoles : les routes
doivent donc être importées.

## Les traductions

Les pages traduisent leurs textes dans le domaine `phil_shifts`, en anglais et en français ; une application remplace une
clé dans ses propres `translations/phil_shifts.<locale>.*`. Les raisons des refus sont des clés du même domaine :
`shift.full`, `shift.conflict`, `swap.stale`, `shifts.busy` et les autres énumérées dans la référence de phil/shifts.
