<!-- phil-dev source-sha256=87206575ef6c07f5fbd0ea1d66166f63c325ed8c77acf1463268dc03e2ef21e8 -->
# Analyse de secrets

Un jeton collé dans un dépôt public est trouvé en quelques minutes, par des personnes comme par des robots. Le programme
de partenaires de l'analyse de secrets de GitHub le trouve aussi, par le motif des jetons de l'application, et l'envoie
à un point de terminaison que l'application enregistre; celui du bundle le révoque sur-le-champ. Le préfixe et la somme
de contrôle du format de jeton rendent le motif précis : une correspondance est un jeton de cette application, ou un
jeton mal tapé que la somme de contrôle refuse déjà.

## L'activer

Le point de terminaison est désactivé par défaut. Il exige `symfony/http-client`, `symfony/cache` et `phil/url`; les
extensions OpenSSL et Sodium, qui vérifient la signature, sont exigées par le bundle :

```sh
composer require symfony/http-client symfony/cache phil/url
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    secret_scanning:
        github: true
```

```yaml
# config/routes/phil_api_keys.yaml
phil_api_keys_github_secret_scanning:
    resource: '@PhilApiKeysBundle/config/github_secret_scanning.php'
    prefix: /hooks/github/secret-scanning
```

La route répond à `POST` seulement, sans état, hors de tout pare-feu qui exige un utilisateur : GitHub signe ses
requêtes, il ne se connecte pas.

```yaml
# config/packages/security.yaml
security:
    firewalls:
        github_secret_scanning:
            pattern: ^/hooks/github/secret-scanning
            security: false
```

Enregistrez ensuite auprès de GitHub, par son programme de partenaires de l'analyse de secrets, le motif des jetons (le
préfixe, un trait de soulignement, 12 caractères de `[0-9A-Za-z]`, un trait de soulignement et 38 autres) et l'adresse
du point de terminaison.

## Ce qu'il fait

| Requête | Réponse |
|---|---|
| Signée par GitHub, une liste de correspondances | `200`, une étiquette par correspondance |
| Pas de `Github-Public-Key-Identifier` ni de `Github-Public-Key-Signature`, une clé que GitHub ne publie pas, une signature qui ne se vérifie pas | `403`, le corps non lu |
| Un corps de plus de 1 Mio | `413` |
| Signée, mais pas une liste de correspondances d'un jeton et d'un type | `400` |

La signature est de l'ECDSA avec SHA-256 sur le corps brut, en base64, sous la clé que GitHub publie avec cet
identifiant. Le corps n'est lu qu'une fois la signature vérifiée, et dans des bornes : 1 000 correspondances au plus, un
jeton de 512 octets au plus, un type de 256.

Chaque correspondance passe par `ApiTokens::revokeLeaked()`, qui vérifie le texte comme le fait `verify()` (sa forme, sa
somme de contrôle, sa ligne, son secret en temps constant) sans enregistrer d'utilisation. Un jeton de cette
application est révoqué, quel que soit son état; quand cela le révoque, `TokenRevoked` puis `TokenLeaked` sont répartis,
chacun avec le seul identifiant du jeton, pour un écouteur qui prévient le propriétaire.

La réponse énumère chaque correspondance par le SHA-256 de son jeton, en hexadécimal, avec le type donné par GitHub et
une étiquette :

```json
[{"token_hash": "3f9c…", "token_type": "acme_api_token", "label": "true_positive"}]
```

`true_positive` pour un jeton de cette application, `false_positive` sinon. La réponse ne contient jamais de jeton.

## Les clés

`GitHubPublicKeys` va chercher les clés à `keys_url`, par défaut
`https://api.github.com/meta/public_keys/secret_scanning` de GitHub, lit 64 Kio et 32 clés au plus, écarte une clé
qu'OpenSSL ne lit pas comme une clé publique, et les garde dans le bassin de cache que nomme `cache` pendant
`keys_lifetime` secondes, partagé par chaque *worker*. Une requête qui nomme une clé que le bassin ne contient pas fait
chercher les clés de nouveau, puisque GitHub les renouvelle, mais au plus une fois par `refresh_interval` secondes : des
requêtes qui nomment des clés inconnues ne peuvent pas faire appeler GitHub à chacune. Une recherche échouée (une
erreur, une redirection, une réponse trop longue ou mal formée) est gardée pendant un `refresh_interval`, durant lequel
chaque requête reçoit la réponse `403`.

## La tester

La suite de tests signe des corps avec une paire de clés qu'elle fabrique et sert sa clé publique par le
`MockHttpClient` de Symfony; une application fait de même avec le service que nomme son `http_client`. Les deux
lecteurs, celui du rapport et celui des clés, sont des cibles de *fuzzing*.
