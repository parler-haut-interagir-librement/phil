<!-- phil-dev source-sha256=adb2ea612ebcf39a4b318ed781c74a30afd7de39001d5b704b758674034dcc8a -->
# Clés

L'application signe avec une seule clé, quel que soit le côté qu'elle joue, et publie un seul jeu de clés à
`phil_lti_jwks`.

## Clés dans la table

Avec `keys.source: dbal` — la valeur par défaut —, le bundle garde ses clés dans la table `phil_lti_key` :

- chaque clé privée est faite par `RsaPrivateKey::generate()` de `phil/lti`, longue de `keys.bits`, et scellée par chiffrement à enveloppe par le KeyManagement de Symfony, sous la clé maîtresse
  `keys.master_key` du KMS `keys.kms`, l'identifiant de clé étant authentifié avec elle ;
- la première clé est fabriquée lorsque la table n'en contient aucune ;
- `phil:lti:rotate-keys` fabrique une nouvelle clé et retire les autres, qui restent publiées pendant `keys.overlap`,
  sept jours par défaut ; `--if-due` ne renouvelle que lorsque la clé de signature est plus vieille que `keys.lifetime`,
  quatre-vingt-dix jours par défaut ;
- chaque exécution de la commande supprime les clés retirées avant le chevauchement.

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[lti]=%env(LTI_MASTER_KEY)%'
```

Le composant KeyManagement est expérimental dans Symfony 8.2 : un adaptateur du bundle, `KeyManagementSealer`, l'appelle.
Une application qui scelle autrement lie par alias `Phil\LtiBundle\Application\Contract\KeySealer` à son propre
service.

La table garde chaque clé privée comme le JSON de sa JSON Web Key privée, scellé ; ouvert, il est relu par
`RsaPrivateKey::fromJson()` comme une clé de `web-token/jwt-library`, sa taille vérifiée.

Les clés ouvertes sont gardées dans le service jusqu'à la fin de la requête ; un *worker* les réinitialise.

## Clés de l'environnement

Avec `keys.source: config`, l'application donne une clé privée en PEM, depuis un secret ou l'environnement, son
identifiant, et les clés publiques des rotations précédentes encore publiées :

```yaml
phil_lti:
    keys:
        source: config
        private_key: '%env(file:LTI_PRIVATE_KEY_FILE)%'
        key_id: '2026-11'
        retired_public_keys: ['%env(file:LTI_PREVIOUS_PUBLIC_KEY_FILE)%']
```

La rotation revient alors à l'application : publiez d'abord la nouvelle clé publique, changez la clé privée, gardez la
clé publique précédente pendant le chevauchement.

## Les clés de l'autre côté

Les jeux de clés des plateformes et des outils sont lus par `phil/safe-http` et gardés une heure dans `cache` ; une clé
inconnue fait relire le jeu, une fois par minute au plus par adresse.
