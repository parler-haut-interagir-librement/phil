<!-- phil-dev source-sha256=f456f6a22952de4e2b4f5c6a30bb2db6fbd95adf316126e531e821379a9a420f -->
# Signaux de confidentialité

**Un navigateur qui envoie Do Not Track ou Global Privacy Control n'est compté dans aucun compteur** : ni l'audience,
ni le compteur des verdicts robot.

État : `PrivacySignals` est implémenté et testé ; `AdmissionRule` l'applique
([collection-and-admission.md](collection-and-admission.md)).

## `PrivacySignals`

```php
$signals = PrivacySignals::fromHeaders(
    $request->headers->get('DNT'),       // Do Not Track
    $request->headers->get('Sec-GPC'),   // Global Privacy Control
);
$signals->doNotTrack;            // bool
$signals->globalPrivacyControl;  // bool
$signals->optOut();              // true if either is set

PrivacySignals::none()->optOut(); // false — for tests, or a request that carries no header
```

| `DNT` | `Sec-GPC` | `optOut()` |
|---|---|---|
| absent | absent | `false` |
| `1` | absent | `true` |
| absent | `1` | `true` |
| `1` | `1` | `true` |
| `0` | absent | `false` |
| ` 1 ` (avec espaces) | absent | `true` |
| `yes` | `true` | `false` |

**Seule la valeur `1`, après retrait des espaces, est un signal.** `DNT: 0` n'exprime de consentement à rien — ce n'est
simplement pas un refus —, et un en-tête absent n'est pas un signal.

## Sa place dans l'admission

Le refus est vérifié juste après l'état du site, **avant** la détection des robots d'indexation, la liste de pourriel
et le verdict robot. Un navigateur qui a refusé ne laisse donc de trace dans aucun compteur, y compris le compte agrégé
des verdicts robot.

Le limiteur de débit passe avant l'admission : une requête qui porte un signal de confidentialité consomme donc quand
même le budget du limiteur et laisse dans son état une empreinte à clé de courte durée ; elle n'atteint jamais un
compteur.

## Ce que fait le script de collecte

Le script de collecte ([collection-and-admission.md](collection-and-admission.md#le-script-de-collecte)) n'envoie rien
quand le navigateur envoie Do Not Track ou Global Privacy Control, ne pose aucun témoin, n'en envoie aucun (un `fetch`
sans identifiants) et n'utilise aucun stockage local. La vérification des en-têtes côté serveur fait foi, quoi que
fasse le script.
