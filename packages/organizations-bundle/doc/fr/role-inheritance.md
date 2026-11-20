<!-- phil-dev source-sha256=1cfa94621b947108a4ccd54d733dea1cf5d71817b03a440004ee8c515b15ace4 -->
# Héritage des rôles

Les rôles d'une unité suivent le modèle des rôles de l'organisation : un **catalogue** associe chaque rôle à des
permissions, la configuration déclare les rôles et les permissions, et les rôles que détient chaque membre sont des
données. Ce qu'une unité ajoute, c'est l'arbre : **un rôle accordé sur une unité vaut dans cette unité et dans chaque
unité en dessous.**

## Le catalogue des unités

`manager` et `member` sont intégrés. `manager` accorde `unit.manage`, la permission de gérer les unités en dessous ;
`member` n'accorde rien par lui-même et dit que le membre appartient à l'unité. La configuration déclare d'autres rôles,
ou ajoute des permissions aux rôles intégrés :

```yaml
phil_organizations:
    unit_roles:
        instructor: { permissions: [courses.assign, reports.read] }
        manager: { permissions: [reports.read] }
```

Un module déclare les permissions qu'il vérifie — `courses.assign` pour un module de cours, `reports.read` pour un
rapport — et l'application les donne à des rôles d'unité.

## Ce qu'un membre détient dans une unité

`Application\UnitAccess` le lit :

```php
$unitAccess->permissions($unit, $member);              // sorted
$unitAccess->can($unit, $member, 'courses.assign');
$unitAccess->hasRole($unit, $member, 'manager');
```

1. Une unité archivée n'accorde rien, pas plus qu'une unité d'une organisation archivée, ni rien à quiconque n'est pas
   membre de l'organisation.
2. Un membre dont les rôles d'**organisation** accordent `units.manage` — les propriétaires et les administrateurs par
   défaut — détient chaque permission du catalogue des unités dans chaque unité.
3. Sinon, un membre détient les permissions de chaque rôle d'unité qu'il détient **dans l'unité ou dans n'importe quelle
   unité au-dessus d'elle**, ensemble.

`hasRole()` dit si le membre détient le rôle dans l'unité ou au-dessus d'elle, que l'unité soit archivée ou non ;
`units.manage`, de l'organisation, ne donne aucun rôle d'unité.

```text
Acme
└── Sales          cam: manager
    ├── East       dan: instructor
    │   └── North  dan: member
    └── West
```

Ici, cam détient `unit.manage` dans Sales, East, North et West ; dan détient `courses.assign` dans East et North, et rien
dans Sales ou West. Un administrateur d'Acme détient chaque permission d'unité partout.

Les permissions au-dessus d'une unité se lisent en une seule requête : le chemin de l'unité nomme ses ancêtres, de sorte
que les adhésions du membre à ces unités, seize au plus, sont une lecture indexée.

## Le voter

`OrganizationVoter`, le *voter* des organisations, vote aussi sur les unités, données comme l'unité, son identifiant ou
le texte de son identifiant :

```php
$this->denyAccessUnlessGranted('unit:courses.assign', $unit);
```

```twig
{% if is_granted('unit:unit.manage', unit) %}…{% endif %}
{% if is_granted('unit_role:manager', unit) %}…{% endif %}
```

`unit:<permission>` suit `UnitAccess::can()`, `unit_role:<role>` suit `UnitAccess::hasRole()`. Quiconque n'est pas
connecté, n'est pas membre, ou interroge une unité inconnue se voit refuser l'accès. Les attributs de l'organisation —
`organization:<permission>`, `organization_role:<role>` — gardent leur sens et ne lisent pas les rôles d'unité.

## Accorder

Un gestionnaire n'accorde, ne change et ne retire que des rôles d'unité dont il détient les permissions dans cette
unité : un gestionnaire sans `courses.assign` ne nomme pas d'instructeur. Un membre dont les rôles d'organisation
accordent `units.manage` accorde chaque rôle d'unité. [Unités](units.md#qui-peut-changer-quoi) liste qui peut changer
quoi.

## Ce que l'héritage ne fait pas

- Un rôle d'unité n'accorde rien dans l'organisation : un gestionnaire de Sales n'invite pas de membres dans Acme. Les
  permissions de l'organisation viennent des rôles d'organisation seulement.
- Les rôles descendent, jamais vers le haut ni sur le côté : un gestionnaire de East ne détient rien dans Sales ou West.
- Les adhésions aux unités ne sont pas publiées comme relations de `phil/access-bundle` ; le *voter* et `UnitAccess` les
  lisent.
