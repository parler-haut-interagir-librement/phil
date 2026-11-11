<!-- phil-dev source-sha256=680e6d1b89c73f278a3f6524afb2496246bd8f4553e0467c3f8813feea0bc33c -->
# Mode maintenance

## L'activer et le désactiver

```sh
bin/console phil:monitoring:maintenance enable                  # visitors told to come back in retry_after seconds
bin/console phil:monitoring:maintenance enable --retry-after=900
bin/console phil:monitoring:maintenance status
bin/console phil:monitoring:maintenance disable
```

Avec le stockage `file`, le site est en maintenance tant que le fichier indicateur existe : un script de déploiement
peut aussi le créer avec `touch var/maintenance.json`. Un fichier vide ou illisible vaut une maintenance depuis sa
dernière modification, avec le `retry_after` configuré. Le fichier est écrit de façon atomique et relu à chaque
requête, pour que chaque *worker* d'un serveur de longue durée le voie apparaître et disparaître. Avec le stockage
`cache`, l'état est une clé de `cache_pool` qui n'expire jamais.

Le service `Maintenance` — `enable()`, `disable()`, `current()` — fait la même chose depuis le code de l'application.

## Ce que voient les visiteurs

Pendant la maintenance, chaque requête principale répond `503 Service Unavailable`, avec `Retry-After` et
`Cache-Control: no-store` :

- en JSON, `{"status":"maintenance","retry_after":600}`, quand la requête préfère le JSON ;
- sinon, le gabarit `maintenance.template`, qui reçoit le `MaintenanceState` sous le nom `maintenance` ;
- du texte brut quand l'application n'a pas Twig.

Le gabarit du bundle est autonome : il n'étend aucune mise en page de l'application, qui pourrait avoir besoin de ce
que la maintenance a retiré. Fournissez le vôtre par `maintenance.template`, sans appel à la base de données.

## Qui voit encore le site

Une requête est servie normalement quand l'une de ces règles le permet, dans cet ordre :

1. sa route fait partie de `allowed_routes`, ou c'est l'un des points de terminaison de santé, toujours servis ;
2. son adresse client fait partie de `allowed_ips`, une adresse ou un réseau CIDR, en IPv4 ou en IPv6 ;
3. son utilisateur reçoit l'un des `allowed_roles` du vérificateur d'autorisation.

L'adresse client est celle que lit Symfony. Derrière un mandataire ou un répartiteur de charge, déclarez-le dans
`framework.trusted_proxies`, sinon chaque requête semble venir de l'adresse du mandataire. Les rôles ne sont consultés
que si l'application a le composant de sécurité, et seulement pour une requête dans un pare-feu qui connaît son
utilisateur.

L'écouteur s'exécute sur `kernel.request` après le routeur et le pare-feu, pour que la route et l'utilisateur soient
tous deux connus. Une sous-requête n'est jamais arrêtée : elle appartient à une requête principale déjà autorisée.
