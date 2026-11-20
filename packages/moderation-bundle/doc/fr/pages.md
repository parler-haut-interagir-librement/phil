<!-- phil-dev source-sha256=fad6b1bbe1784b2778a8704720847fd12f3552976a2ad7a0b9a62fb7c0d70fb6 -->
# Pages

## L'interface d'administration

Les pages sont réservées aux modérateurs : un membre qui n'est pas modérateur se heurte au pare-feu, comme devant toute
page refusée. Chaque formulaire est envoyé avec un jeton CSRF, et chaque page est `no-store`.

| Page | Route | Ce qu'elle fait |
|---|---|---|
| La file | `phil_moderation_queue` | La file unique, une page à la fois ; avec `phil/incident-mode-bundle`, le lien vers sa page et la proposition d'un incident quand les signalements montent en flèche ([Mode d'incident](incident-mode.md)) |
| Une cible | `phil_moderation_entry`, `phil_moderation_decide` | Ce que son paquet en dit, ses signalements ouverts, le formulaire de décision avec les motifs d'un exposé des motifs, son historique |
| Un membre | `phil_moderation_member`, `phil_moderation_note`, `phil_moderation_note_remove` | Les décisions sur son contenu et les notes de la modération à son sujet |

Les pages étendent la mise en page de `admin.layout`, qui définit les blocs `title` et `main`.

## Les exposés des motifs

Le membre à qui un exposé des motifs est adressé le lit sur `phil_moderation_statement`, et tous les siens sur
`phil_moderation_statements` ([Exposés des motifs](statements-of-reasons.md#où-le-membre-le-lit)).

## Notes sur un membre

Les notes de la modération sur un membre sont écrites et lues par les modérateurs seulement. Le membre ne les voit
jamais ; elles se distinguent des notes privées qu'un membre garde pour lui-même, qui relèvent d'un autre paquet. Une
note compte jusqu'à 5 000 caractères.

## Dans une interface d'administration EasyAdmin

Quand EasyAdminBundle est enregistré, le bundle greffe le même contrôleur sur chaque tableau de bord par
`#[AdminRoute]` : les mêmes pages, rôles et formulaires, par les mêmes services, produits dans la mise en page du
tableau de bord, sous des routes nommées d'après celles du tableau de bord — `admin_moderation_queue` sous un tableau de
bord dont la route est `admin`. Le menu du tableau de bord y mène :

```php
use Phil\ModerationBundle\Infrastructure\EasyAdmin\ModerationAdmin;

public function configureMenuItems(): iterable
{
    yield ModerationAdmin::menuItem('admin');
}
```

## Le formulaire de signalement

Une page inclut le formulaire de signalement d'un contenu :

```twig
{{ include('@PhilModeration/_report_form.html.twig', {type: 'forum.post', id: post.id}) }}
```

Le membre choisit une raison et peut ajouter un commentaire ; un membre de confiance voit aussi le bouton qui masque le
contenu. Chaque formulaire revient à la page courante — un chemin du même site, jamais un autre hôte.

Les gabarits du site disposent aussi de `phil_moderation_reasons()`, `phil_moderation_report_token()`,
`phil_moderation_may_hide()` et `phil_moderation_kit('Button')` ; savoir si un incident suspend quelque chose
maintenant, c'est `phil_incident_suspended()`, de `phil/incident-mode-bundle`
([Mode d'incident](incident-mode.md#dans-les-pages-du-site)).

## Le journal public

`phil_moderation_journal` énumère les décisions publiques, la plus récente d'abord : la date, l'action, le type de
contenu, le rôle et la raison publique. **Il ne montre jamais le modérateur, l'auteur ni la note.** Il est le même pour
tout le monde, de sorte qu'un cache partagé le garde une minute.

## Les recettes du kit

| Gabarit | Recettes |
|---|---|
| `admin/queue.html.twig` | `Alert`, `Badge`, `Table` et ses parties |
| `admin/entry.html.twig` | `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Textarea` |
| `admin/member.html.twig` | `Alert`, `Badge`, `Button`, `Field`, `Textarea` |
| `journal.html.twig` | `Badge`, `Table` et ses parties |
| `statements.html.twig` | `Badge`, `Table` et ses parties |
| `statement.html.twig` | `Alert`, `Badge` |
| `_report_form.html.twig` | `Button`, `Field`, `NativeSelect`, `Textarea` |

Les libellés sont traduits dans le domaine `phil_moderation` ; le bundle existe en anglais et en français. Un rôle, une
raison ou un type de cible de l'application sans traduction montre son code.
