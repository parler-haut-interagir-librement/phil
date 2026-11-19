<!-- phil-dev source-sha256=215c2627a80363ca80434290f567f48b77c900f1e0a909f64101dd1ad02d5edd -->
# Assainissement

`ErrorTracker` remet chaque événement au `Scrubber` avant que l'empreinte, le magasin ou l'alerteur ne le voient.

## Ce qui est filtré

| Où | Quoi | Gardé comme |
|---|---|---|
| En-têtes et étiquettes | un nom sensible | le nom, avec `[Filtered]` |
| L'adresse de la requête | chaque valeur de la chaîne de requête, quel que soit son nom | `name=[Filtered]` |
| L'adresse de la requête | le fragment | retiré |
| L'adresse de la requête, `Referer`, `Origin` | le segment qui suit un préfixe de chemin à jeton déclaré | `[Filtered]` |
| `Referer`, `Origin` | chaque valeur de la chaîne de requête, et le fragment | comme pour l'adresse de la requête |
| Chaque texte | une adresse courriel | `[email]` |
| Chaque texte | les identifiants de connexion d'une adresse, `scheme://user:password@` | `scheme://[Filtered]@` |
| Chaque texte | un identifiant de connexion `Bearer`, `Basic` ou `Token` | `Bearer [Filtered]` |
| Chaque texte | une paire `name=value` ou `name: value` au nom sensible | `name=[Filtered]` |

Les textes sont les messages, les valeurs des autres en-têtes et étiquettes, et le chemin de l'adresse. Les cadres
sont laissés tels quels : ils contiennent des chemins et des noms de fonctions, jamais d'arguments.

Un nom est sensible quand il contient, sans égard à la casse, l'un de `pass`, `secret`, `token`, `api-key`, `api_key`,
`apikey`, `auth`, `credential`, `session`, `cookie`, `csrf`, `xsrf`, `private-key`, `private_key`, `signature`,
`card`, `cvv`, `iban`, `ssn`. `Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Csrf-Token` et `user_password`
sont filtrés ; un nom comme `author` l'est aussi, par prudence.

## Champs de l'application

```php
use Phil\ErrorTracking\Scrubber;

$scrubber = new Scrubber(['birth_date', 'x-tenant-secret']);
```

Chaque champ est reconnu comme les valeurs par défaut, en tant que partie d'un nom, sans égard à la casse.

## Jetons dans le chemin

Un jeton dans le chemin, comme celui de `/reset-password/reset/{token}`, survit au filtrage de la chaîne de requête.
Les routes qui en portent un sont déclarées, par le préfixe que le jeton suit :

```php
use Phil\ErrorTracking\Scrubber;

$scrubber = new Scrubber([], ['/reset-password/reset/', '/invitation/']);

$scrubber->url('https://example.com/reset-password/reset/a1b2c3?lang=fr');
// https://example.com/reset-password/reset/[Filtered]?lang=[Filtered]
```

- Un préfixe est un ou plusieurs segments de chemin entre barres obliques. Tout le reste (`reset`, `/reset`,
  `shop/reset/`) lève `InvalidOption`.
- Un préfixe n'est reconnu qu'au début du chemin, après le schéma et l'hôte, sans égard à la casse :
  `/shop/reset-password/reset/x` n'est pas sous `/reset-password/reset/`.
- Quand un préfixe déclaré en commence un autre (`/p/` et `/p/x/`), le segment qui suit chacun est filtré :
  `/p/x/abc` devient `/p/[Filtered]/[Filtered]`.
- L'adresse de la requête, `Referer` et `Origin` sont filtrés ainsi ; un message ne l'est pas, étant du texte libre.

## L'adresse du client

L'assainisseur ne voit jamais d'adresse brute : `RequestSummary::anonymized()` l'a déjà transformée en ce que garde
une stratégie de `phil/ip-anonymizer`, soit une adresse tronquée, une empreinte à clé ou un réseau. Les en-têtes par
lesquels les mandataires transmettent l'adresse brute, soit `Forwarded`, `X-Forwarded-For`, `X-Real-IP`,
`CF-Connecting-IP`, `True-Client-IP`, `X-Client-IP` et les autres de `RequestSummary::CLIENT_ADDRESS_HEADERS`, ne lui
parviennent pas non plus : `RequestSummary` les retire.

Les textes ne sont pas fouillés à la recherche d'adresses : un message qui cite l'adresse d'un serveur, comme une base
de données qui ne répond pas, la garde.

## Ce que l'assainisseur ne peut pas savoir

Un message qui contient en toutes lettres un nom, une adresse postale ou un identifiant de l'application le garde,
au-delà des motifs ci-dessus. Les messages que l'application écrit elle-même sont l'endroit où laisser de côté les
renseignements personnels.
