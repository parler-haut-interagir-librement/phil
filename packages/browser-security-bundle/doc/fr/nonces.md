<!-- phil-dev source-sha256=0234912f0a8fec0123c1947a555f05aaf5812d692c588ce1b2e23df607c9abe7 -->
# Nonces

Un nonce est une valeur aléatoire qu'une réponse donne à ses propres éléments `<script>` et `<style>`, et qu'elle liste
dans sa politique : un élément injecté dans la page ne le connaît pas, et ne s'exécute pas. Le bundle en crée un par
sorte et par requête, 128 bits aléatoires en base64, quand un gabarit ou un service le demande pour la première fois, et
l'ajoute à l'en-tête de la réponse :

```http
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-iI5R8XMt31Pu+l6vYio9mg=='; style-src 'self' 'nonce-6WYimOcmbi+5703clRr3mw=='
```

Une réponse dont les gabarits n'ont demandé aucun nonce n'en reçoit aucun dans son en-tête.

## Dans Twig

```twig
<script nonce="{{ csp_nonce('script') }}">document.documentElement.classList.add('js')</script>
<style nonce="{{ csp_nonce('style') }}">body { margin: 0 }</style>
```

`csp_nonce()` sans argument donne le nonce des scripts. Chaque élément d'une sorte partage le nonce de la réponse.

Un nonce va après les sources de `script-src` ou de `style-src`, et aussi de `script-src-elem` ou de `style-src-elem`
quand la politique les déclare. Quand la politique ne déclare ni la directive ni `default-src`, rien ne restreint les
éléments et aucun nonce n'est ajouté ; quand la directive vaut `'none'`, le nonce n'est pas ajouté non plus : un nonce
n'ouvre jamais ce que la politique a fermé.

## `importmap()` d'AssetMapper

`{{ importmap('app') }}` écrit le nonce des scripts sur chaque balise qu'il écrit : la carte d'importation, le
chargeur du polyfill et les points d'entrée. Le polyfill lui-même reçoit le nonce du chargeur à l'exécution. Un gabarit
qui passe son propre attribut `nonce` le garde. L'option `importmap: false` désactive ce comportement.

## Turbo

Turbo lit la balise meta `csp-nonce` pour donner le nonce de la page aux éléments `<script>` qu'il exécute et au
`<style>` de sa barre de progression :

```twig
<head>
    {{ csp_meta() }}
    {{ importmap('app') }}
</head>
```

`csp_meta()` écrit `<meta name="csp-nonce" content="…">` avec le nonce des scripts, et le bundle permet aussi ce nonce
dans `style-src`, pour que la barre de progression ne soit pas bloquée. Quand Turbo remplace la page, il prend le nonce
de la nouvelle page dans sa balise meta.

## Stimulus

Les contrôleurs Stimulus se chargent par la carte d'importation, sous son nonce, et se connectent par des attributs
`data-controller` : aucun gestionnaire en ligne, rien de plus à permettre. Les contrôleurs qui chargent leurs propres
scripts ont besoin de `'strict-dynamic'` ou des hôtes de ces scripts dans `script-src`.

## `'strict-dynamic'`

```yaml
phil_browser_security:
    policies:
        default:
            strict_dynamic: true
```

ajoute `'strict-dynamic'` à côté des nonces et des empreintes de `script-src` : un script permis par son nonce peut alors
en charger d'autres, et les navigateurs qui comprennent le mot-clé ignorent les hôtes et `'self'` de la liste. Le
mot-clé n'est ajouté que quand la réponse a un nonce de script ou une empreinte, puisque seul il bloquerait tous les
scripts.

## Empreintes

Un élément en ligne connu est permis par le SHA-256 de son contenu, depuis la configuration (voir
[Politiques](policies.md#empreintes-du-code-en-ligne)) ou depuis le code :

```php
$csp->allowInline('script', $code);
```

## Nonces et caches HTTP

Une page avec un nonce est une page pour une seule réponse. Un cache partagé qui la garde sert le même nonce à tout le
monde, ce qui donne à un attaquant qui lit la page un nonce que la politique en cache accepte. Le bundle ajoute donc
`Cache-Control: private` à chaque réponse dont la politique liste un nonce, écrite par le bundle ou par le contrôleur, et
retire `public` ; les autres directives restent. Les pages qu'un cache partagé garde sont servies sans nonce : avec des
empreintes, ou avec des scripts externes seulement.

## Le JSON-LD d'une page

`structured_data()` de phil/structured-data-bundle imprime un bloc de données, qu'une politique n'exécute pas : il ne
demande aucun nonce, et une page dont le seul élément en ligne est ce bloc reste dans les caches partagés. Un gabarit qui
en veut un le passe, `{{ structured_data(csp_nonce('script')) }}`, et la page liste alors un nonce et devient privée,
comme toute autre.
