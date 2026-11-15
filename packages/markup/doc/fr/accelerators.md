<!-- phil-dev source-sha256=37dec2ac541412fa25a34b1b1369fbd0f14290e82eb7a28f89858a314468a882 -->
# Accélérateurs

La bibliothèque fonctionne en PHP simple. Une extension de PHP, quand elle est chargée, assainit plus vite : `ammonia`,
qui analyse avec html5ever, l'analyseur HTML5 de Servo, et réécrit l'arbre à partir de listes d'éléments permis, en
Rust. Rien ne l'exige, et rien ne change pour le lecteur de la page : les deux assainisseurs gardent les mêmes éléments
à chaque niveau.

## `AmmoniaPurifier`

`Adapter\AmmoniaPurifier` implémente `HtmlPurifier` sur l'extension. `defaultPolicies($mediaHosts)` traduit les
politiques de `SanitizerPurifier::defaultPolicies($mediaHosts)` (`SanitizerPurifier::configurations()` les donne) en
une politique de l'extension par niveau : les mêmes éléments et attributs, les mêmes schémas de liens, les liens et
images relatifs, aucun `rel` ajouté aux liens, les commentaires retirés.

```php
use Phil\Markup\Adapter\Purifiers;

$purifier = Purifiers::defaultPolicies();                   // AmmoniaPurifier where the extension is loaded
$purifier = Purifiers::defaultPolicies(['cdn.example.com']); // the hosts images may come from, either way
$purifier = Purifiers::defaultPolicies(extension: false);    // always SanitizerPurifier
```

`AmmoniaPurifier::isAvailable()` dit si l'extension est chargée ; `AmmoniaPurifier::defaultPolicies()` lance
`MissingExtension` quand elle ne l'est pas.

### Ce que gardent les deux

Les deux sorties sont jugées sur des propriétés, pas sur les octets : rien qui s'exécute, les éléments du niveau, les
mêmes éléments une fois assaini de nouveau, et les mêmes éléments que l'autre assainisseur. Les tests donnent aux deux
le même HTML hostile (vecteurs XSS connus, XSS par mutation, schémas d'adresses, éléments qui agissent sur la page,
arbres profonds) à chaque niveau, et la cible de fuzzing `markup-purifier` leur donne des entrées que personne n'a
écrites.

Là où les sorties diffèrent, aucune ne garde quoi que ce soit qui s'exécute :

| Entrée | `SanitizerPurifier` | `AmmoniaPurifier` |
|---|---|---|
| `style` | retiré | retiré ; le CSS relève de `phil/css-sanitizer` |
| `srcset`, `cite`, `poster` et les autres adresses que `href` et `src` | gardés à `all` quand leur schéma est permis | retirés : l'extension ne vérifie que le schéma de `href` et de `src` |
| Le schéma d'une image | `http`, `https`, ou relatif | un schéma de lien, ou relatif : une image `mailto:` ou `tel:`, qu'aucun navigateur ne charge, peut rester |
| Un élément qu'aucune norme ne définit, hors du niveau | retiré avec son texte | retiré, son texte gardé et échappé |
| Les attributs sûrs à `all`, sur les éléments MathML | retirés | gardés, comme sur tout élément HTML |
| Sérialisation | `<br />`, `&#43;` | `<br>`, `+` |

Le SVG en ligne reste à `phil/svg-sanitizer`, comme avec l'assainisseur.

### Ce qui va à l'assainisseur

`AmmoniaPurifier` donne six sortes de HTML au `SanitizerPurifier` des mêmes politiques :

| HTML | Pourquoi |
|---|---|
| Plus court que `$minimumBytes`, 256 octets par défaut (`AmmoniaPurifier::MINIMUM_BYTES`) | L'extension reconstruit sa politique à chaque appel, de 25 à 45 µs, qu'un nom ou une légende ne rembourse pas |
| Plus de 10 000 balises (`AmmoniaPurifier::MAXIMUM_TAGS`) | Le temps d'un analyseur HTML5 croît avec le carré de la profondeur de l'arbre, plus vite dans l'extension que dans l'assainisseur : des éléments `div` imbriqués par dizaines de milliers prennent des secondes |
| Contenant `<noscript` | L'extension lit son contenu comme du texte, comme le fait un navigateur qui exécute des scripts, l'assainisseur comme du balisage : les deux sont inertes, mais ils gardent des éléments différents |
| Qui n'est pas de l'UTF-8 valide | L'extension le refuse ; l'assainisseur donne une chaîne vide |
| Contenant `<input` | L'assainisseur réécrit chaque case à cocher comme la case désactivée d'une liste de contrôle à `all`, et retire tout autre champ `input` |
| À `forum` et `all`, contenant `src`, quand `$mediaHosts` n'est pas `null` | L'extension ne peut pas vérifier l'hôte d'une image |

## Mesures

L'assainissement seul, le HTML que devient un document Markdown compilé, PHP 8.5 ZTS sur FrankenPHP, `PurifierBench` :

| Longueur | Niveau | `SanitizerPurifier` | `AmmoniaPurifier` |
|---|---|---|---|
| 300 octets | `basic` / `forum` / `all` | 38 / 52 / 57 µs | 34 / 34 / 49 µs |
| 12 Ko | `basic` / `forum` / `all` | 0,97 / 1,34 / 1,46 ms | 0,53 / 0,62 / 0,58 ms |
| 100 Ko | `basic` / `forum` / `all` | 8,1 / 10,8 / 12,5 ms | 4,1 / 4,3 / 4,4 ms |

Sur 100 Ko, le pic de mémoire passe de 43–52 Mo à 6,4 Mo. Une page qui compile 10 Ko de Markdown et les assainit passe
la plupart de son temps dans le lecteur : en mode *worker*, l'extension le déplace de quelques pour cent. Le gain est
dans l'assainissement du HTML stocké, `markup_purify` de `phil/markup-bundle`, et dans les longs textes.

Pour mesurer sur votre machine, dans l'image `bench-ext` de `phil/dev-tools`, depuis la racine du monodépôt :

```sh
PHIL_DEV_BENCH_EXT_PIE="switon/ammonia" vendor/bin/phil-dev bench:worker phil/markup --ext --build
vendor/bin/phpbench run packages/markup/tests/Benchmark/PurifierBench.php --report=phil
```

## Installer l'extension

L'extension est le paquet Composer `switon/ammonia`, installé par PIE :

```sh
pie install switon/ammonia
```

PIE prend un binaire précompilé quand il correspond à PHP, ou le compile avec Rust 1.85 ou plus récent. Dans
`composer.json`, la bibliothèque la suggère comme `ext-ammonia`.

| Contrainte | Détail |
|---|---|
| PHP | 8.2 à 8.5, avec ou sans sécurité des threads (ZTS, comme FrankenPHP l'exécute) ; **pas 8.6** : sous 8.6, la bibliothèque utilise l'assainisseur, et les tests de l'extension ne s'exécutent pas |
| Systèmes | Précompilée pour Linux avec glibc, x86_64 ou arm64, glibc d'Ubuntu 24.04 ou plus récente ; macOS sur puce Apple ; Windows x64. Ailleurs, Alpine (musl) compris, PIE la compile |
| Chargement | Par chemin : `extension=/path/to/libammonia.so` (`.dylib` sur macOS, `ammonia.dll` sur Windows) ; `extension=ammonia` cherche un autre nom de fichier. PIE écrit lui-même la ligne |

L'assainisseur en Rust n'a pas de corruption de mémoire propre ; la liaison entre PHP et Rust, écrite avec
`ext-php-rs`, n'a pas été auditée. Chargez l'extension là où le gain est mesuré, et gardez l'assainisseur ailleurs.

## Dans Symfony

`phil/markup-bundle` choisit avec son option `purifier` : `auto` par défaut, `sanitizer`, ou `ammonia`.
