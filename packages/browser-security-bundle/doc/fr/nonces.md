<!-- phil-dev source-sha256=38f8845ec8d1a94fcb225d5340cedbae6689e98cb52c0b0e868eb90c8429cfdd -->
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

## La page d'erreur

La page d'erreur HTML du composant ErrorHandler, que FrameworkBundle affiche quand aucun gabarit de l'application ne
répond, porte les nonces de la réponse : le bundle remet `scriptNonce()` et `styleNonce()` de
`ContentSecurityPolicyContext` à `HtmlErrorRenderer::setNonce()` (Symfony 8.2), lus quand une page est produite. Son
élément `<style>`, ainsi que les éléments `<script>` et `<style>` de la page d'exception et de ses vidages, s'exécutent
sous une politique sans `'unsafe-inline'`. En mode débogage, l'ErrorListener de HttpKernel retire la politique d'une
page d'erreur, comme le fait Symfony sans le bundle.

## Une politique de transition sans nonces

Un site dont les anciens gabarits écrivent encore des scripts et des gestionnaires en ligne ne peut pas passer aux nonces
partout d'un coup : les navigateurs qui comprennent les nonces et les empreintes ignorent `'unsafe-inline'` dès qu'une
directive en liste un. Une politique déclarée avec `nonces: false` liste les empreintes de la requête mais jamais ses
nonces, pour que ces pages gardent une politique pendant que le reste du site garde ses nonces :

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"], object-src: ["'none'"] }
            strict_dynamic: true
        transitional:
            extends: default
            directives:
                script-src: ["'self'", "'unsafe-inline'"]
                style-src: ["'self'", "'unsafe-inline'"]
            nonces: false
```

Une route ou un contrôleur la choisit comme toute autre politique, voir [Surcharges](overrides.md) :

```php
#[ContentSecurityPolicy(policy: 'transitional')]
public function archive(): Response
```

Quand chaque politique que la réponse envoie, appliquée et en mode rapport seulement, est déclarée avec `nonces: false`,
aucun nonce n'est créé :

- `csp_nonce()` donne `null`, et `csp_meta()` n'écrit rien ;
- `importmap()` écrit ses balises sans attribut `nonce`, sauf si le gabarit en passe un ;
- `ContentSecurityPolicyContext::nonce()`, `scriptNonce()`, `styleNonce()` et `sharedNonce()` retournent `null`, et la
  page d'erreur HTML écrit ses éléments sans attribut `nonce` ;
- la réponse, qui ne liste aucun nonce, n'est pas rendue privée.

`html_attr()` de twig/html-extra omet un attribut dont la valeur est `null` : un gabarit écrit pour les deux sortes de
pages n'écrit donc l'attribut que lorsqu'il y a un nonce :

```twig
<script {{ html_attr({nonce: csp_nonce('script')}) }}>document.documentElement.classList.add('js')</script>
```

Sans twig/html-extra, `{% if csp_nonce('script') is not null %}` fait de même ; un simple `nonce="{{ csp_nonce() }}"`
écrit un attribut vide, qui n'autorise rien.

Quand la réponse envoie aussi une politique qui prend des nonces — une politique en mode rapport seulement qui essaie la
politique stricte sur les mêmes pages, par exemple —, les gabarits reçoivent leurs nonces et seule cette politique les
liste. Les gabarits suivent les surcharges connues au moment où ils sont rendus : une surcharge ajoutée ensuite ne change
pas ce qu'ils ont écrit. Les empreintes de `inline` et de `allowInline()` restent dans une politique sans nonces ; un
navigateur qui les comprend ignore alors `'unsafe-inline'` dans cette directive, de sorte qu'une page de transition n'en
liste aucune.

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
