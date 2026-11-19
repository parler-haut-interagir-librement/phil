<!-- phil-dev source-sha256=4be82b9b8e3052316aebbb298f26e23d203df41361b1ad60f057f561364bfeeb -->
# Administration

L'administration liste les liens ; elle les crée, les modifie, les active, les désactive et les supprime, et elle
dessine leurs codes QR.

| Route | Chemin sous le préfixe | Rôle |
|---|---|---|
| `phil_short_link_admin_list` | `/` | Les liens, du plus récent au plus ancien, 50 par page, avec une recherche sur le code, le libellé et la cible ; chacun avec ses visites de personnes sur son maximum, et ses visites de robots à part |
| `phil_short_link_admin_create` | `/new` | Le formulaire d'un nouveau lien |
| `phil_short_link_admin_edit` | `/{id}/edit` | Le formulaire d'un lien, son adresse et son code QR |
| `phil_short_link_admin_toggle` | `/{id}/toggle` | Active ou désactive un lien (`POST`) |
| `phil_short_link_admin_delete` | `/{id}/delete` | Supprime un lien (`POST`) |
| `phil_short_link_admin_qr_code` | `/{id}/qr-code.svg`, `/{id}/qr-code.png` | Le code QR de l'adresse d'un lien |

## Les visites

La liste montre deux comptes pour chaque lien. Les **visites** sont les visites de personnes, que le maximum compte,
écrites `12 / 500` quand le lien en a un. Les **robots** sont les visites de machines — robots d'indexation, scripts,
moniteurs, prélectures, aperçus de liens des services de messagerie —, redirigées mais jamais comptées dans le
maximum. Un lien publié dans un clavardage achalandé a souvent plus de robots que de visites dans ses premières
minutes. Voir [Résolution](resolution.md#les-personnes-et-les-machines).

## Le rôle et les jetons

Chaque page a besoin de `admin.role`, `ROLE_ADMIN` par défaut : un utilisateur qui ne l'a pas rencontre le pare-feu,
comme sur toute page refusée. Les boutons postent un jeton CSRF ; le formulaire porte le jeton des formulaires de
Symfony quand ils en ont un.

## Le formulaire

`Infrastructure\Form\ShortLinkType` modifie un `ShortLinkDraft` : la cible, le code — laissé vide pour en tirer un, et
affiché mais non modifiable une fois que le lien existe —, un libellé, la locale quand un domaine en a une, le statut de
redirection, si la chaîne de requête de la visite est gardée, si le lien est activé, son début et sa fin, écrits dans
`admin.timezone`, et son nombre maximal de visites. `ShortLinks` vérifie le brouillon à son enregistrement ; ses refus
s'affichent sur le champ qu'ils concernent. Le type de formulaire est enregistré aussi pour les formulaires propres à
l'application.

## Les pages

Les pages étendent `admin.layout`, dont le bloc `content` les contient. Elles emploient les noms de classes du kit
PHIL UX — `isTable`, `isField`, `isButton`, `isAlert`, `isPagination`… —, que sa feuille de style dessine, sans exiger
les composants du kit. Leurs mots sont les messages du domaine `phil_short_link`, en anglais et en français. Les
gabarits sous `@PhilShortLink/admin/` peuvent être remplacés dans `templates/bundles/PhilShortLinkBundle/admin/`.

## Les codes QR

Le code QR contient l'adresse du lien sur le domaine de sa locale, dessinée par phil/qr-code avec les options de
`qr_code` : un SVG nommé pour les lecteurs d'écran par l'adresse, ou un PNG à imprimer.
`Application\ShortLinkQrCodes` les dessine aussi depuis le code.
