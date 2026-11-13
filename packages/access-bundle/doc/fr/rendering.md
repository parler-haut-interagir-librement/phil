<!-- phil-dev source-sha256=a93a84e750dd1b6de7c03879d9e3fdcd47b9f8fbebeda8a82c2772ddae454c58 -->
# Rendu

Les deux composants du bundle sont dessinés par le serveur avec les recettes du kit PHIL UX, installées dans
l'application par le Symfony UX Toolkit ; ils n'ont besoin d'aucun JavaScript.

| Composant | Dessine | Recettes |
|---|---|---|
| `<twig:PhilAccess:ViewAsBanner />` | Pendant qu'un administrateur voit le site comme un membre : une alerte avec le membre, une ligne qui dit que la vue ne fait que lire, et le formulaire qui l'arrête. Rien autrement. | `Alert`, `Alert:Title`, `Alert:Description`, `Button` |
| `<twig:PhilAccess:AccessSummary item="page:42" />` | Qui a accès à un élément : sa visibilité comme insigne, puis ses partages — membre ou groupe, niveau, fin — dans un tableau. Seulement pour ceux qui détiennent le niveau le plus large sur l'élément. | `Badge`, `Table` et ses parties |

`item` est une `ResourceReference`, un `AccessibleItem` ou son texte. Les recettes se trouvent dans le sous-répertoire
de la configuration `ux_toolkit` de l'application — `ui:Alert` avec `ui` —, ou de `phil_access.kit_subdirectory`.

La bannière a le rôle `status` : un lecteur d'écran l'annonce. Le tableau du résumé a une légende, et chaque fin est un
`<time>` avec son instant lisible par machine, écrit en UTC.

Les textes sont dans le domaine de traduction `phil_access`, en anglais et en français ; une application remplace un
gabarit sous `templates/bundles/PhilAccessBundle/components/`.
