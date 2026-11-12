<!-- phil-dev source-sha256=f166b029516fef1a6f8543c921bca408e1644eec83244c8089603b7a78c2bf14 -->
# Points de terminaison

Un chemin par brouillon, `/drafts/{key}`, sous le préfixe avec lequel l'application importe `config/routes.php`. La clé
compte de 16 à 64 caractères sûrs pour une URL. C'est une empreinte à clé : aucun renseignement personnel ne circule
dans l'URL.

| Route | Méthode | Corps | Réponses |
|---|---|---|---|
| `phil_forms_autosave_draft` | `GET` | | `200` `{"revision", "savedAt", "version", "fields"}`, ou `204` quand aucun brouillon n'est conservé |
| `phil_forms_autosave_draft_save` | `PUT` | `{"revision", "version", "fields"}` | `200` `{"revision"}` ; `409` `{"error", "revision"}` ; `413` ; `400` |
| `phil_forms_autosave_draft_discard` | `DELETE` | | `204` |

`revision` est la révision que le navigateur a lue, ou null ; `fields` est un objet de listes de textes, par nom de
champ. Chaque réponse porte `Cache-Control: no-store, private`.

## À qui répondre

Un point de terminaison répond `403`, sauf si la requête :

- porte l'en-tête `X-Phil-Forms-Autosave: 1`, qu'un formulaire ou un lien d'un autre site ne peut pas ajouter ;
- n'est pas marquée `Sec-Fetch-Site: cross-site` ;
- pour `PUT` et `DELETE`, passe la vérification CSRF sans état de Symfony pour le jeton `phil_forms_autosave` : un
  nouveau jeton dans un en-tête nommé par le témoin de connexion que le formulaire donne au contrôleur, et dans un
  témoin de ce nom, ou les en-têtes `Sec-Fetch-Site`, `Origin` ou `Referer` du navigateur qui nomment ce site ;
- vient d'un utilisateur que `DraftOwner` nomme.

Les routes restent derrière le pare-feu de l'application ; un limiteur de débit de l'application peut se placer devant
elles.
