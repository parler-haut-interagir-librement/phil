<!-- phil-dev source-sha256=4df6d0685c27deabfb5a07e457f210fba9b93522ab6c31d387dfb4e9c9fe2844 -->
# Points d'extension

Le bundle est dans ses versions `0.x` : sa configuration, ses ports, ses événements et ses services peuvent changer dans
une version mineure, ce que le CHANGELOG indique. Ils sont destinés à être stables à partir de `1.0`.

## Ports

| Port | Valeur par défaut | Remplacez-le pour |
|---|---|---|
| `Application\Contract\SamlAccounts` | aucune : un `SamlUser` dans la session | trouver et créer les comptes de l'application ; voir [Comptes et rôles](accounts.md) |
| `Application\Contract\EndpointUrls` | `RoutedEndpoints`, à partir du routeur | servir les routes sous des adresses que le routeur n'écrit pas |

## Services

Les services sont enregistrés sous leur nom de classe :

- `Phil\SamlSp\Contract\ReplayGuard` : un alias de `Phil\SamlSp\Store\DbalReplayGuard` sur la connexion Doctrine DBAL,
  ou de `Phil\SamlSp\Store\CacheReplayGuard` sur `cache_pool`, selon le choix de `replay_guard` ; une application qui
  définit elle-même ce service ou cet alias garde sa propre garde, que le bundle ne remplace ni ne vérifie ;
- `Phil\SamlSp\Store\CachePendingRequests` sur `cache_pool` : décorez-le ou remplacez-le (voir les points d'extension de
  phil/saml-sp) ;
- `Phil\SamlSp\Metadata\MetadataLoader` et `Phil\SamlSp\Http\SafeHttpMetadataFetcher` : remplacez le récupérateur pour
  lire les métadonnées depuis un magasin de l'application ;
- `Phil\SamlSpBundle\Application\IdentityProviders` : les fournisseurs d'identité, leurs réglages, leurs métadonnées et
  le fournisseur de service que chacun voit ;
- `Phil\SamlSpBundle\Application\SamlLogin`, `SamlLogout` et `SamlUsers` : la connexion, la déconnexion et les
  utilisateurs, pour une application qui construit ses propres pages autour d'eux.

## Événements

Voir [Événements et console](events.md).

## Faire le pont avec un autre paquet de connexion

Le bundle se suffit à lui-même : il connecte les gens avec son authentificateur et donne à l'application une
`SamlIdentity`. Un paquet qui réunit déjà plusieurs façons de se connecter — fournisseurs OpenID Connect, connexions
sociales — peut prendre SAML comme une de plus, sans que le bundle le sache, de deux façons :

- un adaptateur du port de fournisseur de ce paquet qui envoie la personne à `phil_saml_sp_login` avec une `target` qui
  lui est propre, et lit l'événement `SamlLoginSucceeded` pour lui remettre la `SamlIdentity`, associée à son propre type
  d'identité ;
- ou une implémentation de `SamlAccounts` qui délègue `find()` et `create()` à la liaison de comptes de ce paquet, de
  sorte qu'une personne connectée par SAML aboutisse au même compte que par tout autre fournisseur.

Dans les deux cas, le sujet sur lequel lier est la clé du fournisseur d'identité et `SamlIdentity::subject()`.
