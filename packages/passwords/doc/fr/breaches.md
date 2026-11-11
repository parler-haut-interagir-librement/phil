<!-- phil-dev source-sha256=68c71a7ad0ef42b1bbf36d8d66500574790681111cd3a6b94ceee39c08693b8b -->
# Vérifier les fuites

## Le k-anonymat

`PwnedPasswordsSource` calcule l'empreinte SHA-1 du mot de passe, envoie les cinq premiers caractères hexadécimaux de
l'empreinte à l'API par plages et cherche les 35 autres dans la réponse. Le service ne voit jamais le mot de passe ni
son empreinte, seulement un préfixe partagé par des centaines d'autres. Le remplissage est demandé par défaut
(`Add-Padding: true`) : la taille de la réponse ne révèle ainsi rien du préfixe à qui observe le trafic. Ici, SHA-1
est l'adresse du mot de passe dans le corpus, pas une protection : rien dans la bibliothèque ne stocke de mot de passe.

```php
$checker = new BreachChecker(new PwnedPasswordsSource($httpClient));
$check = $checker->check($password);             // threshold 1
$check = $checker->check($password, threshold: 10);
```

| Membre de `BreachCheck` | Ce qu'il contient |
|---|---|
| `status` | `BreachStatus::Compromised`, `NotFound` ou `Unavailable`. |
| `occurrences` | Les occurrences signalées par la source, 0 quand elle n'a pas pu répondre. |
| `failure` | La `BreachCheckUnavailable` d'une source qui n'a pas pu répondre, null sinon. |
| `isCompromised()`, `isUnavailable()` | Des raccourcis. |

## La source

| Argument de `PwnedPasswordsSource` | Valeur par défaut | Effet |
|---|---|---|
| `client` | — | Le client HTTP, de `symfony/http-client-contracts`. |
| `baseUrl` | `https://api.pwnedpasswords.com/range/` | Un miroir qui sert le même format. |
| `padding` | `true` | Demande des réponses avec remplissage. |
| `timeout` | 5 secondes | Le délai d'inactivité ; la requête entière est bornée au double. |
| `userAgent` | `phil-passwords` | Le service refuse les requêtes qui n'en ont pas. |
| `maxBytes` | 1 Mio | La plus grande réponse lue ; une plage avec remplissage fait quelques dizaines de kilooctets. |

## Échecs

Une source qui ne peut pas répondre — le réseau, un statut autre que 200, un délai dépassé, une réponse trop grande ou
mal formée — lance `BreachCheckUnavailable`, et `BreachChecker` la transforme en statut `Unavailable` avec l'échec.
Accepter alors le mot de passe ou non relève de la décision de l'application : la contrainte `NotCompromised` le
refuse par défaut (échec fermé) et l'accepte avec `skipOnError`. Le mot de passe ne figure jamais dans le message de
l'échec.

`BreachCheckUnavailable` est la seule exception que compte un disjoncteur placé autour de la source. Un décorateur du
port empêche d'appeler la source tant que le circuit est ouvert :

```php
final readonly class GuardedSource implements BreachedPasswordSource
{
    public function __construct(private BreachedPasswordSource $inner, private CircuitBreaker $breaker)
    {
    }

    public function occurrences(#[SensitiveParameter] string $password): int
    {
        if (!$this->breaker->isAvailable()) {
            throw new BreachCheckUnavailable('The breach source is paused.');
        }

        try {
            $occurrences = $this->inner->occurrences($password);
        } catch (BreachCheckUnavailable $failure) {
            $this->breaker->recordFailure();

            throw $failure;
        }

        $this->breaker->recordSuccess();

        return $occurrences;
    }
}
```

## Autres sources

`InMemoryBreachSource` garde des empreintes en mémoire : `fromPasswords()` pour une liste de mots de passe et leurs
occurrences, `fromHashes()` pour des empreintes SHA-1, comme une liste importée pour un réseau sans accès à Internet.
Toute classe qui implémente `BreachedPasswordSource` est une source ; `RangeResponse::occurrences()` lit une plage pour
une source qui la télécharge autrement.
