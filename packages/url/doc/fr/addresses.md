<!-- phil-dev source-sha256=b8e994b72575afa35ef871646bc328b2f5f6f2eb9f50898e717886ea41acb062 -->
# Adresses

`Url::fromString()` lit une adresse Web absolue comme un navigateur la lit : l'extension URI de PHP l'analyse selon le
WHATWG URL Standard, `Uri\WhatWg\Url`, puis `Url` applique ses propres règles ci-dessous.

| Accepté | Refusé |
|---|---|
| `http` et `https`, dans n'importe quelle casse | Tout autre schéma, une adresse sans schéma, du texte autour |
| Un nom d'hôte fait d'étiquettes jointes par des points, internationales comprises | Un nom seul (`localhost`), une adresse IP (`http://192.168.0.1/`, `http://[::1]/`), un domaine de premier niveau fait de chiffres, un trait de soulignement, une étiquette vide (`https://.`, `https://shop..example/`, `https://example.com../`), une étiquette de plus de 63 caractères, un hôte de plus de 253 |
| Un port de 1 à 65535, un chemin, une chaîne de requête, un fragment | Le port 0, des espaces, un nom d'utilisateur ou un mot de passe (`https://ada@…`, `https://@…`), une troisième barre oblique ou une barre oblique inverse avant l'hôte (`https:///path`, `https://\example.com/`) |
| 2048 caractères au plus, tel qu'écrit et une fois normalisé | Plus long : `<` écrit 1000 fois devient `%3C` 1000 fois |

Chaque refus lance `InvalidUrl`, une `UrlException`.

## Normalisation

| Lu | Gardé comme |
|---|---|
| `HTTPS://Example.COM/Shop` | `https://example.com/Shop` : le chemin garde sa casse |
| `https://example.com` | `https://example.com/` |
| `https://example.com:443/` | `https://example.com/` : le port par défaut retiré |
| `https://bücher.example/` | `https://xn--bcher-kva.example/` |
| `https://example.com./?#` | `https://example.com/` : un seul point final, chaîne de requête et fragment vides retirés |
| `https://example.com/a/./b/../c` | `https://example.com/a/c` : les segments `.` et `..` résolus |
| `https://example.com/a/%2e%2E/b`, `/a/.%2e/b`, `/a/%2e./b` | `https://example.com/b` : des points encodés sont des points |
| `https://example.com\a\..\b` | `https://example.com/b` : une barre oblique inverse est une barre oblique, comme dans un navigateur |
| `https://example.com/a%2Fb` | `https://example.com/a%2Fb` : une barre oblique encodée reste dans son segment |
| `https://example.com/<script>"` | `https://example.com/%3Cscript%3E%22` |
| `https://example.com/thé?q=<b>#"x"` | `https://example.com/th%C3%A9?q=%3Cb%3E#%22x%22` |

Le chemin, la chaîne de requête et le fragment sont encodés en pourcentage selon les ensembles du WHATWG URL
Standard : dans le chemin, l'espace, `"`, `#`, `<`, `>`, `?`, l'accent grave, `{`, `}` et tout caractère hors de
l'ASCII imprimable ; dans la chaîne de requête, l'espace, `"`, `#`, `<`, `>`, `'` et les caractères hors de l'ASCII
imprimable ; dans le fragment, l'espace, `"`, `<`, `>`, l'accent grave et les caractères hors de l'ASCII imprimable.
Une lettre hors de l'ASCII est encodée en UTF-8. Un encodage déjà écrit reste tel qu'écrit, ni décodé ni mis en
majuscules : `%7e` et `~` sont deux adresses différentes pour `equals()`, et `%2F` est un caractère de son segment,
jamais un séparateur, si bien que `segments()` lit `/docs/..%2Fadmin` comme `['docs', '..%2Fadmin']`. Un `..` dans la
chaîne de requête ou le fragment est du texte, gardé tel qu'écrit.

`equals()` compare des adresses normalisées.

## Permettre des chemins par préfixe

`path` et `segments()` donnent le chemin que le navigateur ouvre, si bien qu'on ne peut pas sortir d'un préfixe
vérifié sur eux : `https://example.com/docs/../admin`, `/docs/%2e%2e/admin` et `/docs\..\admin` ont tous le
chemin `/admin`.

```php
use Phil\Url\Url;

function isDocumentation(string $text): bool
{
    $url = Url::fromString($text);

    return $url->isOn('example.com') && 'docs' === ($url->segments()[0] ?? null);
}

isDocumentation('https://example.com/docs/install'); // true
isDocumentation('https://example.com/docs/../admin'); // false: its path is /admin
```

Vérifiez le préfixe sur `Url`, jamais sur le texte d'où il a été lu.

## La lire

| Membre | Donne |
|---|---|
| `scheme`, `host`, `port`, `path`, `query`, `fragment` | Les parties ; `port` vaut `null` pour le port par défaut |
| `toString()` | L'adresse normalisée |
| `display()` | Telle que les gens la lisent : `example.com/shop` pour `https://www.example.com/shop/`, `bücher.example` pour `https://xn--bcher-kva.example/` ; un hôte dont les lettres mélangent des écritures ou se font passer pour celles d'un autre reste en ASCII ; les lettres encodées du chemin et de la chaîne de requête sont montrées en lettres, `example.com/thé`, alors qu'un encodage d'ASCII, un caractère invisible, une espace ou un caractère de contrôle reste encodé |
| `isOn(...$domains)` | Si l'hôte est l'un de ces domaines ou en fait partie : `m.facebook.com` est sur `facebook.com` |
| `segments()` | Les segments du chemin, les segments vides retirés |
