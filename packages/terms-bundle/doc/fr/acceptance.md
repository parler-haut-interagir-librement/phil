<!-- phil-dev source-sha256=8735c743deae17338a3f621f8cb7ab16cc6752ee5c285655194e03d050dcba56 -->
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
    $signupAcceptances->record($form, $member->id, $request->getClientIp());
}
```

## Depuis le code

```php
use Phil\TermsBundle\Domain\AcceptanceChannel;

$terms->accept($memberId, 'terms', 3, AcceptanceChannel::Api, $request->getClientIp());
```

Une acceptation porte sur la version en vigueur ou sur une version plus récente déjà publiée ; une version remplacée
par une plus récente est refusée avec la raison `superseded_version`. `Terms` répartit `TermsAccepted` — des
identifiants seulement — sur le répartiteur d'événements.
