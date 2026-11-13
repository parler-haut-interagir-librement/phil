<!-- phil-dev source-sha256=fdd766de326771fdada911f2913caf2081cafc977b739775edcf1017f65b0dbd -->
# Assainissement

`ErrorTracker` remet chaque événement au `Scrubber` avant que l'empreinte, le magasin ou l'alerteur ne le voient.

## Ce qui est filtré

| Où | Quoi | Gardé comme |
|---|---|---|
| En-têtes et étiquettes | un nom sensible | le nom, avec `[Filtered]` |
| L'adresse de la requête | chaque valeur de la chaîne de requête, quel que soit son nom | `name=[Filtered]` |
| L'adresse de la requête | le fragment | retiré |
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

## L'adresse du client

L'assainisseur ne voit jamais d'adresse brute : `RequestSummary::anonymized()` l'a déjà transformée en ce que garde
une stratégie de `phil/ip-anonymizer`, soit une adresse tronquée, une empreinte à clé ou un réseau.

## Ce que l'assainisseur ne peut pas savoir

Un message qui contient en toutes lettres un nom, une adresse postale ou un identifiant de l'application le garde,
au-delà des motifs ci-dessus. Les messages que l'application écrit elle-même sont l'endroit où laisser de côté les
renseignements personnels.
