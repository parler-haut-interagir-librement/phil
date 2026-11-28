<!-- phil-dev source-sha256=b569b6f42c1c8dce8ad831716006a89520f4621a3cfa894d5125647edc31c5b6 -->
# L'agent

L'agent est la moitié du bundle qu'un site exécute pour la console. Il répond à quatre points de terminaison sous le
préfixe avec lequel ses routes sont importées :

| Point de terminaison | Méthode | Ce qu'il fait |
|---|---|---|
| `status` | `GET` | Les versions et la santé du site. |
| `configuration` | `POST` | Applique des réglages communs, une fois par poussée. |
| `update` | `POST` | Amène un paquet à une version, une fois par étape d'un plan. |
| `keys` | `POST` | Dépose ou retire une clé de la console, une fois par étape d'une rotation de clé. |

Chaque requête doit être signée par la console pour ce site, dans la fenêtre de dates, avec un nonce jamais vu ;
sinon elle reçoit `401` et rien d'autre ne se passe. Chaque réponse à une requête vérifiée est signée par le site. Voir
[Modèle de sécurité](security-model.md).

## status

```json
{
    "site": "shop",
    "generated_at": "2026-11-20T10:00:00+00:00",
    "php": "8.5.1",
    "symfony": "8.2.0",
    "packages": {"acme/catalog": "1.4.2", "symfony/http-kernel": "v8.2.0"},
    "health": {"status": "up", "checks": [{"name": "database", "status": "up", "summary": ""}]}
}
```

Les paquets viennent de `composer.lock`, lu à chaque requête : ceux de `packages`, et de `packages-dev` avec
`include_dev`, limités aux motifs de `packages` quand il y en a. Un fichier de verrouillage absent ou mal formé ne donne
aucun paquet. La santé vient des vérifications de `phil/monitoring-bundle` quand son bundle est enregistré, exécutées
quand la console le demande ; sans lui, elle est `unknown`.

## configuration

```json
{"id": "01JD…", "settings": {"mail.footer": "Hello"}}
```

L'agent refuse toute la poussée avec `422` quand une clé n'est pas permise par `configuration_keys`, et avec `501`
quand le site n'a pas de `ConfigurationApplier`. Sinon il appelle l'applicateur une fois pour cet identifiant et
répond :

```json
{"outcome": "succeeded", "replayed": false, "message": "1 settings applied."}
```

La même poussée livrée de nouveau répond le même résultat avec `"replayed": true`, sans appeler l'applicateur. Le même
identifiant avec d'autres réglages est refusé avec `422` ; la même poussée encore en cours d'application reçoit `409`
avec `Retry-After`. Un applicateur qui lève une exception donne `"outcome": "failed"`, l'erreur va aux journaux du
site, et la poussée peut être livrée de nouveau.

### Écrire un applicateur

```php
use Phil\SitesConsoleBundle\Application\Contract\ConfigurationApplier;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;

final readonly class SettingsApplier implements ConfigurationApplier
{
    public function __construct(private SettingsStore $store)
    {
    }

    public function apply(Settings $settings): void
    {
        foreach ($settings->values as $key => $value) {
            $this->store->set($key, $value);
        }
    }
}
```

Nommez son service dans `agent.configuration_applier`. Les clés qu'il reçoit sont seulement celles que
`configuration_keys` permet.

## update

```json
{"id": "01JD…:shop", "package": "acme/catalog", "version": "1.4.3"}
```

L'agent appelle son `UpdateRunner` une fois pour cet identifiant et répond son résultat. Le bundle n'exécute jamais
Composer : l'exécuteur lance le propre pipeline du site — une tâche de déploiement, un script sur un serveur de
construction —, l'attend dans la limite de `update_timeout` de la console, et dit comment cela s'est passé. Sans
exécuteur, les mises à jour reçoivent `501`.

```php
use Phil\SitesConsoleBundle\Application\Contract\UpdateRunner;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;

final readonly class PipelineRunner implements UpdateRunner
{
    public function __construct(private Pipeline $pipeline)
    {
    }

    public function run(string $package, string $version): Outcome
    {
        $job = $this->pipeline->update($package, $version);

        return $job->succeeded() ? Outcome::succeeded('Deployed.') : Outcome::failed($job->summary());
    }
}
```

Un résultat échoué est gardé et répondu de nouveau pour la même étape ; un exécuteur qui lève une exception donne un
échec que la même étape peut retenter.

## keys

```json
{"id": "<rotation>:stage:shop", "operation": "stage", "key": "<public key, base64url>"}
```

La rotation de clé de la console poste ici ; rien d'autre n'en a besoin. `stage` fait de la clé la prochaine clé de la
console que le site accepte, en plus de la courante, et doit être signé avec la clé courante. `retire` cesse d'accepter
une clé et doit être signé avec la clé qui reste : retirer la clé courante fait de la suivante la courante, et retirer
l'unique clé est refusé. Une clé déjà déposée, déjà courante ou non détenue ne change rien et réussit. La réponse est un
résultat, comme pour une mise à jour ; chaque identifiant n'est appliqué qu'une fois.

Les clés sont gardées dans le pool de `agent.key_pool`, ou à défaut de `agent.cache_pool`, qui doit être partagé par les
serveurs du site et survivre aux déploiements. Les clés configurées l'emportent sur le pool dès qu'elles changent. Chaque changement est relu une fois écrit : un
changement que le pool n'a pas conservé fait échouer la requête, et la rotation de la console s'arrête là, la clé
retirée restant de confiance et signalée comme telle. Voir
[Modèle de sécurité](security-model.md#rotation-des-cles).

## Erreurs

| Statut | Signée | Quand |
|---|---|---|
| `400` | oui | Un corps qui n'est pas le document de l'action. |
| `401` | non | Une requête que la console n'a pas signée pour ce site, maintenant, une seule fois. |
| `409` | oui | La même poussée ou la même étape encore en cours ; `Retry-After` donne les secondes restantes. |
| `413` | non | Un corps de plus de 64 Kio, déclaré par `Content-Length` ou lu ; jamais plus de 64 Kio et un octet ne sont lus. |
| `422` | oui | Une clé de réglage non permise, un changement de clé de la console signé par la mauvaise clé ou qui retire l'unique clé, ou un identifiant réutilisé avec un autre corps. |
| `501` | oui | Aucun applicateur, ou aucun exécuteur. |
| `503` | non | Un nonce que le site n'a pas pu retenir, son pool ou son magasin de verrous hors service ; `Retry-After` donne 60 secondes. |

Chaque corps d'erreur est `{"error": "<message>"}`.
