<!-- phil-dev source-sha256=3d367143af476ed951b7828643c17b831d9aca0a6082396baa9dcb02ba6aee41 -->
# JavaScript

## Le contrôleur

`assets/src/autosave_controller.js`, enregistré sous le nom `phil--forms-autosave--autosave`, s'exécute sur la balise du
formulaire, où l'option `autosave` le place.

| Valeur | Valeur par défaut | |
|---|---|---|
| `key` | | La clé du brouillon |
| `version` | | La version de la structure du formulaire |
| `ttl` | `604800` | Les secondes pendant lesquelles un brouillon est conservé |
| `debounce` | `800` | Les millisecondes après la dernière modification avant un enregistrement ; jamais moins de 100 |
| `serverDebounce` | `5000` | Les millisecondes après le dernier enregistrement avant l'envoi du brouillon au serveur ; jamais moins de 1 000 |
| `maxBytes` | `262144` | La taille maximale d'un brouillon conservé |
| `endpoint` | | L'URL du brouillon sur le serveur ; sans elle, les brouillons restent dans le navigateur |
| `csrf` | | Le nom du témoin CSRF du point de terminaison |
| `invalid` | `false` | Le formulaire revient d'une soumission invalide |
| `labels` | Anglais | Les textes de la région active, de l'avis et de ses boutons |

À sa connexion, le contrôleur oublie les brouillons dont le serveur a dit qu'ils avaient été envoyés et lit le brouillon
de sa clé. Sauf si le formulaire revient invalide, il le remet en place et affiche l'avis. Avec un point de terminaison,
il lit ensuite le brouillon du serveur : il le remet en place quand le navigateur n'en avait aucun, et le signale comme
plus récent quand c'est une autre révision que celle lue par ce navigateur et qu'il contient d'autres valeurs. Chaque
`input` ou `change` enregistre une fois la frappe arrêtée ; `submit` et `pagehide` enregistrent tout de suite.

Ses actions sont celles des boutons de l'avis :

| Action | |
|---|---|
| `discard` | Remet les valeurs avec lesquelles la page a été affichée et retire le brouillon, ici et sur le serveur |
| `dismiss` | Masque l'avis ; après un conflit, les valeurs du formulaire remplacent le brouillon plus récent au prochain enregistrement |
| `useServer` | Met dans le formulaire le brouillon plus récent du serveur |

Il émet `phil--forms-autosave--autosave:restored`, avec les champs remis en place dans `detail.fields`, et
`phil--forms-autosave--autosave:discarded`. Chaque champ qu'il modifie reçoit un événement `change` qui se propage.

## L'état

La région active est la cible `status`, `role="status"` et `aria-live="polite"` : elle annonce chaque enregistrement,
une restauration, un abandon, un conflit, un brouillon trop gros ou un navigateur qui refuse le stockage. L'avis est la
cible `notice`, avec son `message` et les boutons `useServer`, `discard` et `dismiss`, chacun affiché seulement quand il
s'applique. Le gabarit du bundle et l'`AutosaveStatus` des trousses les dessinent ; sans eux, le contrôleur ajoute les
siens en haut du formulaire.

## Les modules

| Module | |
|---|---|
| `lib/fields.js` | Les champs conservés, leurs valeurs lues et réécrites, la comparaison de deux ensembles, les éditeurs remontés |
| `lib/storage.js` | Les brouillons dans le stockage local sous `phil-autosave:<key>`, chaque accès protégé ; le témoin des formulaires envoyés |
| `lib/debounce.js` | Une étape exécutée une fois les modifications arrêtées, forcée ou annulée |
| `lib/csrf.js` | Un nouveau jeton pour chaque requête qui modifie un brouillon, dans un en-tête et un témoin |
| `lib/server.js` | `DraftClient` : lire, enregistrer par révision, abandonner ; une requête échouée reçoit une réponse, jamais une exception |
| `lib/ui.js` | Les libellés anglais, la fusion des libellés de la page, l'état et l'avis construits quand le gabarit n'en a pas |

## Stockage local

Un brouillon est stocké sous la forme `{"v": 1, "version", "savedAt", "revision", "fields"}`. Une fenêtre privée, un
stockage bloqué ou un quota plein laisse le formulaire fonctionner sans brouillon, et l'état le dit. Un brouillon d'une
autre version, plus ancien que `ttl`, daté de plus d'une minute dans le futur, ou qui n'est pas un brouillon du tout est
retiré à la lecture.
