<!-- phil-dev source-sha256=cac46b20df010f93884479687ebcdddb6ddb93416f0513f353dba439b3761fd3 -->
# Échantillonnage et limitation

## L'échantillonneur

`Sampler` garde une part des événements, tirée avec `Random\Randomizer` : `1.0` les garde tous, `0.25` environ un sur
quatre, `0.0` aucun. Un taux hors de 0.0 à 1.0 lève `InvalidOption`. Le traqueur tire avant toute autre chose, pour
qu'un événement laissé de côté ne coûte rien de plus.

```php
use Phil\ErrorTracking\Sampler;

$sampler = new Sampler(0.25);
```

Un test donne au générateur un moteur doté d'un germe, comme `new Randomizer(new Mt19937(42))`, pour des tirages qu'il
peut répéter.

## Le limiteur

`CaptureThrottle` borne les événements gardés pour une clé dans un intervalle de temps. Le traqueur l'interroge avec
l'empreinte, après l'assainisseur, pour qu'une erreur levée à chaque requête écrive un nombre borné de lignes par
groupe. L'interroger compte l'événement.

```php
interface CaptureThrottle
{
    public function allow(string $key): bool;
}
```

Sans limiteur, chaque événement que garde l'échantillonneur est écrit. `phil/error-tracking-bundle` en fournit un sur
`symfony/rate-limiter`.
