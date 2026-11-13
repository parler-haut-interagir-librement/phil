<!-- phil-dev source-sha256=44008e6fa5c37e495270d2a9f6c676b936fd3c54e3e834e9f19ec23cc628f147 -->
# Le thème

`phil-ux-easyadmin/phil-ux-easyadmin.css` donne aux variables d'EasyAdmin les valeurs des rôles de PHIL UX. Il change
seulement des valeurs : EasyAdmin garde son balisage, ses composants et ses gabarits.

## Les couches

EasyAdmin met chaque style de l'administration dans des couches de cascade, déclarées dans cet ordre :
`ea-overrides, ea-defaults, vendor, ea`, avec `ea` divisée en `ea.tokens`, `ea.base`, `ea.prose`, `ea.components` et
`ea.utilities`. Une couche plus tardive l'emporte ; un style hors de toute couche l'emporte sur toutes.

| Partie de la feuille de style | Couche | Effet |
|---|---|---|
| La palette, les rôles de couleur et les dimensions de phil/ux-css | `vendor.phil.palette`, `vendor.phil.colors`, `vendor.phil.dimensions` | Les propriétés personnalisées `--phil-*`, qu'aucun style d'EasyAdmin ne définit ; tout le reste qu'elles définissent perd contre EasyAdmin |
| Le thème | `phil-easyadmin`, déclarée après `ea` | Les variables d'EasyAdmin sur les rôles de PHIL UX, l'emportant sur `ea.tokens` |
| Les styles propres à l'application | aucune | L'emportent sur le thème et sur EasyAdmin |

Les couches de base, de mise en page, de composants et d'utilitaires de phil/ux-css sont écartées. Leurs règles sur les
éléments — `button`, `input`, `code`, un `form div` — restyleraient le balisage d'EasyAdmin depuis toute couche déclarée
après `ea`, et leurs classes ne sont pas dans le balisage d'EasyAdmin. Les boutons, les alertes et les badges de
l'administration sont ceux d'EasyAdmin, aux couleurs de PHIL UX.

La feuille de style est un seul fichier : `bin/build.php` l'écrit depuis `assets/src/phil-ux-easyadmin.css`, chaque
importation d'un fichier de jetons étant remplacée par ce fichier dans sa couche, si bien que l'administration charge
une seule feuille de style et qu'AssetMapper ne résout aucune importation entre deux paquets.

## Les variables

| Variables d'EasyAdmin | Rôles de PHIL UX |
|---|---|
| `--ea-primary`, `--ea-primary-foreground` | `--phil-color-brand-fill-loud`, `--phil-color-brand-on-loud` |
| `--ea-primary-on-surface` | `--phil-color-link` |
| `--ea-primary-mix-base`, `--ea-primary-contrast-base`, dont EasyAdmin tire ses teintes de l'accent | `--phil-color-surface`, `--phil-color-text` |
| `--body-bg`, `--content-bg`, `--sidebar-bg`, `--secondary-bg` | `--phil-color-surface`, `--phil-color-surface-raised`, `--phil-color-neutral-fill-quiet` |
| `--sidebar-menu-color`, `--sidebar-menu-active-item-bg`, `--sidebar-menu-active-item-color` | `--phil-color-text`, `--phil-color-brand-fill-quiet`, `--phil-color-brand-on-quiet` |
| `--text-color`, `--text-muted`, `--link-color`, `--link-hover-color`, `--border-color` | `--phil-color-text`, `--phil-color-text-muted`, `--phil-color-link`, `--phil-color-border` |
| `--table-cell-border-color`, `--table-hover-cell-bg` | `--phil-color-border`, `--phil-color-neutral-fill-quiet` |
| `--form-input-border-color`, `--form-input-text-color` | `--phil-color-neutral-border-loud`, `--phil-color-text` |
| `--alert-<state>-bg`, `-color`, `-border-color` pour primary, secondary, success, info, warning et danger | Le fond discret, le texte dessus et la bordure discrète des rôles brand, neutral, success, info, warning et danger |
| `--badge-<state>-bg`, `-color` pour les mêmes états | Le fond discret et le texte dessus des mêmes rôles |
| `--ea-radius`, `--border-radius-sm`, `--border-radius`, `--border-radius-lg`, `--border-radius-xl` | `--phil-radius-md`, `--phil-radius-sm`, `--phil-radius-md`, `--phil-radius-lg`, `--phil-radius-xl` |
| `--ea-focus-ring-width` | `--phil-focus-width` |
| `--font-family-sans-serif`, `--font-family-monospace` | `--phil-font-sans`, `--phil-font-mono` |

Les paires que le thème définit — un fond et le texte dessus — sont les paires dont phil/ux-css vérifie le contraste :
4,5:1 pour le texte dans les deux schémas. L'espacement, la hauteur des contrôles et la taille des polices d'EasyAdmin
sont gardés : l'administration reste aussi dense qu'EasyAdmin la fait.

## Le mode sombre

EasyAdmin choisit le schéma — clair, sombre ou celui du système — et l'inscrit sur le corps de la page : la classe
`ea-dark-scheme` et la propriété `color-scheme`. Les rôles de phil/ux-css sont des paires `light-dark()`, qui prennent
le ton du `color-scheme` de l'élément qui les utilise, si bien que le thème suit le choix de la personne qui utilise
l'administration, même contre celui du système, sans règle par schéma. Le thème déclare ses variables sur
`:root, .ea-dark-scheme`, comme EasyAdmin déclare les siennes, si bien qu'elles s'appliquent dans les deux schémas. Un
tableau de bord qui désactive le mode sombre obtient les tons clairs.

Les icônes des cases cochées et des boutons radio sont des images dont EasyAdmin écrit la couleur dans l'image : claire
sur l'accent dans le schéma clair, sombre sur l'accent dans le schéma sombre. L'accent de PHIL UX suit la même règle, si
bien qu'elles gardent leur contraste.

## Changer le thème

| Pour changer | Écrivez, hors de toute couche, dans une feuille de style du tableau de bord |
|---|---|
| Un rôle, pour le site et son administration | `:root, .ea-dark-scheme { --phil-color-brand-fill-loud: light-dark(…, …); }` |
| Une variable d'EasyAdmin | `:root, .ea-dark-scheme { --sidebar-bg: …; }` |
| Une règle d'EasyAdmin | Le sélecteur, comme la documentation d'EasyAdmin le décrit |

Une application dont les styles sont dans une couche de cascade à elle déclare cette couche après `phil-easyadmin`. Les
options de `Theme` d'EasyAdmin (`primaryColor()`, `grays()`, `radius()`, `font()`) sont écrites hors de toute couche :
quand un tableau de bord les définit, elles l'emportent sur le thème.
