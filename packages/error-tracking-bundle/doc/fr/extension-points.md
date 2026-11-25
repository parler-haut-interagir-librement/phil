<!-- phil-dev source-sha256=bfdb203fe8e3a617b0b5565d60047929b20bdd9927fbe25bb6f67b37e5cd736e -->
# Points d'extension

## Le magasin

`ErrorStore` (de `phil/error-tracking`) et `ErrorGroupReader` (de ce bundle, dans
`Phil\ErrorTrackingBundle\Application\Contract`) sont des alias de `DbalErrorStore`. Une application qui garde ses
erreurs ailleurs remplace les deux alias par un service qui implémente les deux interfaces :

```php
interface ErrorGroupReader
{
    /** @return list<ErrorGroup> */
    public function groups(?GroupStatus $status, int $limit, int $offset = 0): array;

    public function countGroups(?GroupStatus $status): int;

    /** @return list<ErrorEvent> */
    public function events(string $fingerprint, int $limit): array;

    public function purge(DateTimeImmutable $before): int;
}
```

`groups()` donne les groupes, les derniers vus d'abord ; `events()` les derniers d'abord ; `purge()` retire les
événements plus anciens que l'instant donné et les groupes qui n'ont pas été vus depuis, et retourne le nombre de
lignes retirées.

## L'alerteur

L'alerteur du traqueur est un `GroupAlerter` de `phil/error-tracking` : `NotificationAlerter`, `MessengerAlerter` ou
`ChatterAlerter`, selon la configuration, ou `UndeliveredAlerter` dans l'état dégradé, quand aucun d'eux n'a de
destination. Une application qui alerte autrement décore `Phil\ErrorTracking\ErrorTracker`
avec ses propres arguments, ou implémente `GroupAlerter` et le passe comme septième argument de la définition du
traqueur. Une alerte en file d'attente parvient à `AlertErrorGroupHandler`, dont le second argument est l'alerteur qui
envoie.

## La vérification de santé

Avec phil/monitoring-bundle installé et activé, `ErrorStoreCheck` est un `HealthCheck` nommé `error_tracking`,
autoconfiguré. Il lit `ErrorGroupReader::groups()`, `NewGroupsThreshold::MAX` (200) groupes au plus, et il est dégradé
quand le magasin ne répond pas ou quand `health_check.new_groups` groupes sont apparus en `health_check.window`
secondes ; seuls des comptes parviennent à son résumé. Sans ce bundle, la vérification n'est pas enregistrée et rien
d'autre n'en est chargé : les options `health_check` sont toujours lues, et le bundle s'installe et se compile seul.

## Exceptions ignorées

`ignored_exceptions` nomme les classes jamais capturées, avec leurs sous-classes : une exception que l'application lève
exprès et traite ailleurs, un délai d'attente dépassé attendu.

## L'assainisseur

`scrub_fields` ajoute des parties de nom aux parties sensibles ; voir le document sur l'assainissement de
`phil/error-tracking`. Un assainisseur propre à l'application remplace le service `Phil\ErrorTracking\Scrubber`.

`token_path_prefixes` déclare les routes qui portent un jeton dans leur chemin, par le préfixe que le jeton suit :
avec `/reset-password/reset/` déclaré, `/reset-password/reset/a1b2c3` est gardé comme
`/reset-password/reset/[Filtered]`, dans l'adresse de la requête, dans `Referer` et dans `Origin`. Un préfixe n'est
reconnu qu'au début du chemin. Un jeton dans le chemin survit au filtrage de la chaîne de requête : chaque route de
l'application qui en porte un, comme une réinitialisation de mot de passe, une invitation ou un lien partagé par son
jeton, a sa place dans la liste :

```yaml
phil_error_tracking:
    token_path_prefixes: ['/reset-password/reset/', '/invitation/']
```

Les en-têtes par lesquels les mandataires transmettent l'adresse brute du client, soit `X-Forwarded-For`,
`Forwarded`, `X-Real-IP`, `CF-Connecting-IP`, `True-Client-IP`, `X-Client-IP` et les autres, ne sont jamais gardés ;
l'adresse du client est gardée telle que `client_address` la tronque.

## La mise en page et les gabarits

`admin.layout` donne aux pages la mise en page de l'application, qui définit les blocs `title` et `main` ; les gabarits
peuvent être remplacés sous `templates/bundles/PhilErrorTrackingBundle/`. Avec EasyAdminBundle enregistré, les mêmes
pages sont greffées sur chaque tableau de bord, et `ErrorTrackingAdmin::menuItem()` donne au menu du tableau de bord
son lien. Voir [Administration](administration.md).
