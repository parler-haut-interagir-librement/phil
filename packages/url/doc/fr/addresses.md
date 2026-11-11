<!-- phil-dev source-sha256=e65674a80492547c004529c9647d60c40bed7c72a2c28ecd59cb8a7123a6c2ce -->
# Adresses

`Url::fromString()` lit une adresse Web absolue.

| Accepté | Refusé |
|---|---|
| `http` et `https`, dans n'importe quelle casse | Tout autre schéma, une adresse sans schéma, du texte autour |
| Un nom d'hôte fait d'étiquettes jointes par des points, internationales comprises | Un nom seul (`localhost`), une adresse IP (`http://192.168.0.1/`, `http://[::1]/`), un domaine de premier niveau fait de chiffres, un trait de soulignement, une étiquette vide (`https://.`, `https://shop..example/`, `https://example.com../`), une étiquette de plus de 63 caractères, un hôte de plus de 253 |
| Un port de 1 à 65535, un chemin, une chaîne de requête, un fragment | Le port 0, des espaces, un nom d'utilisateur ou un mot de passe (`https://ada@…`) |
| 2048 caractères au plus | Plus long |

Chaque refus lance `InvalidUrl`, une `UrlException`.

## Normalisation

| Lu | Gardé comme |
|---|---|
| `HTTPS://Example.COM/Shop` | `https://example.com/Shop` : le chemin garde sa casse |
| `https://example.com` | `https://example.com/` |
| `https://example.com:443/` | `https://example.com/` : le port par défaut retiré |
| `https://bücher.example/` | `https://xn--bcher-kva.example/` |
| `https://example.com./?#` | `https://example.com/` : un seul point final, chaîne de requête et fragment vides retirés |

`equals()` compare des adresses normalisées.

## La lire

| Membre | Donne |
|---|---|
| `scheme`, `host`, `port`, `path`, `query`, `fragment` | Les parties ; `port` vaut `null` pour le port par défaut |
| `toString()` | L'adresse normalisée |
| `display()` | Telle que les gens la lisent : `example.com/shop` pour `https://www.example.com/shop/`, `bücher.example` pour `https://xn--bcher-kva.example/` ; un hôte dont les lettres mélangent des écritures ou se font passer pour celles d'un autre reste en ASCII |
| `isOn(...$domains)` | Si l'hôte est l'un de ces domaines ou en fait partie : `m.facebook.com` est sur `facebook.com` |
| `segments()` | Les segments du chemin, les segments vides retirés |
