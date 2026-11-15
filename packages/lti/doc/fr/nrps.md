<!-- phil-dev source-sha256=8eeae39c72c11522fa32abe8ddbf99890afad0dfff6a03f437277120052ee442 -->
# Names and Role Provisioning

Le Names and Role Provisioning Service liste les membres d'un contexte — leur identifiant, leurs rôles, leur statut, et
ce que la plateforme partage de leurs noms — pour qu'un outil puisse préparer des groupes, des tableaux de bord ou des
listes avant que chaque membre lance l'outil.

## Du côté de l'outil

```php
use Phil\Lti\Service\Nrps\NrpsClient;

$nrps = new NrpsClient(new ServiceCall($client, $tokens));

foreach ($nrps->members($platform, $launch->message->nrps, role: 'Learner', limit: 100) as $member) {
    $member->userId;        // the `sub` of the member's launches
    $member->roles->isLearner();
    $member->status;        // Active, Inactive or Deleted
}
```

`members()` lit page après page, la suivante trouvée dans l'en-tête `Link` de chaque réponse, et s'arrête sur une
`ServiceFailed` au-delà de 100 pages ; `page()` lit une page et donne son `next` et ses `differences`, pour un outil qui
lit de plus grands contextes par étapes ou qui suit les changements. Chaque page contient 1 000 membres au plus. Le
jeton demande la portée `MEMBERSHIP_READ_ONLY`.

## Du côté de la plateforme

```php
use Phil\Lti\Platform\Nrps\NrpsService;

$page = new NrpsService($roster)->page($token, $contextId, role: $role, limit: $limit, offset: $offset);
```

`page()` vérifie la portée, demande au `Roster` si l'outil est placé dans le contexte, et donne le contexte, jusqu'à
`limit` membres — 100 par défaut, 1 000 au plus — et le décalage de la page suivante, null sur la dernière.
L'application répond les membres en JSON du type de média `application/vnd.ims.lti-nrps.v2.membershipcontainer+json`,
avec un en-tête `Link` de relation `next` construit à partir du décalage.

Le `Roster` appartient à l'application : il connaît ses contextes, les outils placés dans chacun, et leurs membres. Il
ne partage avec un outil que ce dont l'outil a besoin : toujours l'identifiant et les rôles, les noms et le courriel
lorsque l'outil a leur confiance.
