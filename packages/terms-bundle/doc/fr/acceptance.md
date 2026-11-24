<!-- phil-dev source-sha256=cf1e343cf0de431eb634f673dcd5f03dcf2242402bfe2fce6d80fa30ed04a50c -->
# Acceptation

## Ce qu'un membre doit accepter

Pour chaque politique requise, dans l'ordre de la configuration, un membre doit accepter la version en vigueur quand il
n'a pas accepté la version exigée ou une version plus récente — ou quand la politique est renouvelée et que sa
dernière acceptation de ce genre est plus ancienne que son nombre de jours. `AcceptanceChecker::pending()` donne ces
versions.

## La porte

`AcceptanceGate` écoute `kernel.request` après le pare-feu. Pour un membre connecté qui a une version à accepter :

- une page reçoit une réponse `303 See Other` vers la page d'acceptation, avec `?target=` l'adresse qu'il a demandée ;
- une API reçoit une réponse `403` avec un problème RFC 9457 en `application/problem+json` :

```json
{
    "type": "about:blank",
    "title": "Terms to accept",
    "status": 403,
    "detail": "A version of the terms awaits your acceptance.",
    "accept": "/terms/accept",
    "pending": [{"policy": "terms", "version": 3, "url": "/terms/terms/v3"}]
}
```

Une requête est celle d'une API quand son chemin se trouve sous l'un des `gate.api_paths`, que son format est JSON, ou
que son premier type acceptable est JSON. Un visiteur, une sous-requête, les routes du bundle lui-même
(`phil_terms_*`), les `gate.exempt_routes` et les chemins sous `gate.exempt_paths` passent : une route de déconnexion,
les ressources statiques de la page et une vérification de santé y ont leur place.

Chaque requête lit une fois les versions de chaque politique, puis pose au stockage une question par politique requise,
sur l'index du membre, de la politique et de la version.

## La page d'acceptation

`phil_terms_accept` affiche le texte de chaque version à accepter, chacune avec sa propre case à cocher, jamais cochée
d'avance, et un lien permanent vers la version. Le formulaire n'est accepté que si chaque case est cochée pour la
version affichée : une version publiée entre-temps est affichée de nouveau, avec un `422`, plutôt que considérée comme
acceptée. Chaque acceptation est conservée avec le canal `form`, puis le membre retourne à `target` — un chemin du site
seulement, `/` pour tout le reste. Un visiteur est refusé. Le formulaire porte un jeton CSRF d'identifiant
`phil_terms` ; un formulaire sans jeton valide est refusé avec un `400`.

## Le formulaire d'inscription

```php
$builder = $this->createFormBuilder($member, ['terms_acceptance' => true]);    // every required policy
$builder = $this->createFormBuilder($member, ['terms_acceptance' => ['terms', 'code_of_conduct']]);
```

Chaque politique qui a une version en vigueur ajoute une case à cocher non associée `phil_terms_accept_<policy>`,
jamais cochée d'avance, avec le libellé `signup.accept`, et le numéro de la version affichée dans un champ caché
`phil_terms_version_<policy>`. Le formulaire n'est valide que si chaque case est cochée et que la version affichée est
toujours celle en vigueur. Une fois le membre créé :

```php
use Phil\TermsBundle\Infrastructure\Form\SignupAcceptances;

if ($form->isSubmitted() && $form->isValid()) {
    $member = $members->register($form->getData());
    $signupAcceptances->record($form, $member->id, $request->getClientIp(), $request->getLocale());
}
```

## Depuis le code

```php
use Phil\TermsBundle\Domain\AcceptanceChannel;

$terms->accept($memberId, 'terms', 3, AcceptanceChannel::Api, $request->getClientIp(), $request->getLocale());
```

Une acceptation porte sur la version en vigueur ou sur une version plus récente déjà publiée ; une version remplacée
par une plus récente est refusée avec la raison `superseded_version`. `Terms` répartit `TermsAccepted` — des
identifiants seulement — sur le répartiteur d'événements.

## Prouver le texte accepté

Chaque acceptation garde deux choses à côté du numéro de version :

| Champ | Colonne | Quoi |
|---|---|---|
| `locale` | `locale` | La locale dont le texte a été montré au membre : la locale donnée à `accept()` quand la version a son texte, sinon `default_locale`, sinon la première locale de la version |
| `textSha256` | `text_sha256` | Le SHA-256, en 64 chiffres hexadécimaux minuscules, du HTML que `PolicyTexts::toHtml()` produit à partir de ce texte |

La page d'acceptation donne à `accept()` la locale de la requête, dont elle a montré le texte ; le formulaire
d'inscription et un appel depuis le code la donnent de la même façon. Sans locale, c'est le texte de `default_locale`
qui est consigné. Une version n'est jamais modifiée une fois publiée, de sorte que le numéro de version nomme déjà les
textes ; la locale dit lequel a été lu, et le SHA-256 dit quel HTML le site en a produit.

Pour prouver ce qu'un membre a accepté :

1. Lisez les acceptations du membre — `AcceptanceRepository::ofMember()`, ou l'exportation de `phil/privacy-bundle`.
2. Trouvez la version — `VersionCatalog::find($acceptance->policy, $acceptance->version)` — et son texte dans
   `$acceptance->locale` : c'est le texte accepté.
3. Vérifiez la preuve avec `Terms::textMatches($acceptance)` : `true` quand le HTML de ce texte produit aujourd'hui le
   SHA-256 gardé, `false` quand ce n'est pas le cas ou que la version a disparu, `null` pour une acceptation consignée
   sans preuve.

```php
$acceptance = $acceptances->ofMember($memberId)[0];

if (true === $terms->textMatches($acceptance)) {
    $text = $catalog->find($acceptance->policy, $acceptance->version)?->text($acceptance->locale, $acceptance->locale);
}
```

`false` sur une version toujours dans le stockage signifie que le rendu a changé — un nouveau `phil/ujo` qui produit
autrement un document UJO (Universal JSON Output), ou un autre service `PolicyTexts` —, non que le texte a changé : le
texte de la version est gardé tel qu'il a été publié. Gardez le HTML des rendus passés hors du site quand un litige peut
en avoir besoin, ou produisez le texte avec la version du moteur de rendu qui servait le jour de l'acceptation, dont le
SHA-256 correspond alors.
