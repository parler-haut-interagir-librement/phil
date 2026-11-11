<!-- phil-dev source-sha256=410242ac87afcf916d860e5e691ed90f6b33af2a7dc534026886f03fcbd11684 -->
# Disjoncteurs

Les disjoncteurs sont `phil/circuit-breaker` et son bundle, `phil/circuit-breaker-bundle` : la machine à états, les
politiques, le magasin des états, les clients HTTP derrière leurs circuits et le panneau du profileur leur
appartiennent. Ce bundle ajoute une seule chose : **un indicateur force l'ouverture d'un circuit**, au moyen de la même
bascule auditée que tout indicateur.

## L'indicateur d'ouverture forcée de chaque circuit

Chaque circuit déclaré à `phil_circuit_breaker` a un indicateur, `circuit-open.<key>`
(`FlagCircuitOverride::flagFor($key)`) :

```yaml
phil_circuit_breaker:
    circuits:
        http.payments:
            failure_rate_percent: 50
            minimum_calls: 10
            window_seconds: 60
            open_seconds: 30
            http_client: payments.client
```

déclare `circuit-open.http.payments` dans le fournisseur de configuration, **inactif, sans règles, de genre `ops`**,
avec la description `Forces the circuit "http.payments" open.`, sauf si `phil_feature_flags.flags` le déclare. Les clés
atteignent le fournisseur au moyen de `ForceOpenFlagsPass`, une fois que les deux bundles ont chargé leur configuration.
Une clé de circuit compte au plus 87 octets : `circuit-open.<key>` est donc toujours un nom d'indicateur valide d'au
plus 100 octets.

## Forcer l'ouverture d'un circuit

`FlagCircuitOverride` est le `CircuitOverride` de `phil/circuit-breaker`, enregistré par ce bundle avec l'étiquette
`phil_circuit_breaker.override` : le bundle de disjoncteurs l'interroge à chaque appel à un disjoncteur. Il signale un
circuit forcé ouvert tant que l'indicateur du circuit est activé. Un circuit forcé :

- est indisponible quoi qu'en disent ses compteurs, et son état se lit `open` ;
- n'enregistre rien : ses compteurs reprennent donc là où ils étaient lorsque le forçage est levé ;
- refuse les requêtes de son client HTTP avant qu'elles partent, avec `CircuitOpen` ;
- reste confiné à sa propre clé : il n'arrête jamais un sous-système entier.

Le forcer ouvert est une **bascule** de `circuit-open.<key>` (autorisée, avec une raison, auditée) à partir de
l'administration ou de `FlagToggler` (qui exige `toggle` et un magasin d'états). L'autorisateur peut exiger une
confirmation plus forte pour ces indicateurs ([extension-points.md](extension-points.md#toggleauthorizer)). Un
indicateur d'ouverture forcée n'accepte ni règle ni expérience : un circuit est forcé ouvert pour toutes les requêtes ou
pour aucune.

Le forçage lit l'indicateur au moyen de `FlagEvaluator` : il est donc évalué une fois par requête, comme tout
indicateur, et un circuit forcé prend effet à partir de la requête (ou du message) suivante. Un indicateur qu'aucun
fournisseur ne déclare est désactivé : un circuit dont l'indicateur manque n'est donc jamais forcé ouvert.

Déclarer `circuit-open.<key>: true` dans `phil_feature_flags.flags` force plutôt l'ouverture du circuit par un
déploiement.

## Sans le bundle de disjoncteurs

Lorsque `PhilCircuitBreakerBundle` est enregistré, ses circuits reçoivent leurs indicateurs et le forçage est interrogé.
Lorsqu'il ne l'est pas, aucun circuit n'est déclaré, aucun indicateur d'ouverture forcée n'est ajouté et le forçage
n'est jamais interrogé ; les indicateurs fonctionnent de la même façon.
