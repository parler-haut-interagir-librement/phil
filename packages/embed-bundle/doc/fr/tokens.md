<!-- phil-dev source-sha256=aa0ca77dec283dcbcf82625b00907eaa7ba9b5680a552eadd7070fe8a606d604 -->
# Jetons

Un jeton d'intégration est la capacité que porte la page d'un partenaire : cette intégration, pour cette origine, dans ce
thème, au sujet de ceci, jusqu'à telle échéance.

```text
pe1.hs256.2026-11.eyJlIjoiY2FsZW5kYXIiLCJvIjoiaHR0cHM6Ly9wYXJ0bmVyLmV4YW1wbGUiLCJpIjoxNzk0NTUwNDAwfQ.<signature>
```

## Le format

| Partie | Contient |
|---|---|
| `pe1` | Le format et sa version. |
| `hs256` | L'algorithme : HMAC-SHA-256. |
| `2026-11` | L'identifiant de la clé qui l'a signé. |
| revendications | Du JSON en base64url sans remplissage. |
| signature | Le HMAC-SHA-256 de tout ce qui la précède, en base64url sans remplissage. |

La version du format et l'algorithme ouvrent le jeton et sont signés avec lui : on ne peut changer ni l'un ni l'autre,
ni les ramener à un autre. Un jeton d'un autre format ou d'un autre algorithme est refusé avant que quoi que ce soit
d'autre soit lu ; la signature est comparée en temps constant ; un jeton fait 2 048 octets au plus, et ses revendications
forment un objet JSON de valeurs scalaires.

| Revendication | Est |
|---|---|
| `e` | L'intégration. |
| `o` | L'origine, telle que l'écrit `phil/url`. |
| `i` | Le moment de l'émission, en secondes depuis 1970. |
| `t` | Le thème, lorsqu'un thème a été nommé. |
| `s` | Le sujet, un identifiant de l'application, de 1 à 200 octets. |
| `x` | Le moment de l'expiration, lorsque l'intégration a un `ttl`. |

## Les clés

Les clés forment une liste ordonnée : la première signe chaque nouveau jeton, chaque clé vérifie les jetons qui la
nomment. Pour effectuer la rotation :

1. placez une nouvelle clé en tête ; les nouveaux jetons sont signés avec elle, les anciens se lisent encore ;
2. donnez de nouveaux codes aux partenaires ;
3. retirez l'ancienne clé : les jetons qu'elle a signés cessent de se lire.

```yaml
phil_embed:
    keys:
        - { id: '2027-01', secret: '%env(EMBED_KEY)%' }
        - { id: '2026-11', secret: '%env(EMBED_KEY_PREVIOUS)%' }
```

## Ce que peut faire un jeton copié

Un jeton se trouve dans le HTML du partenaire, où tout le monde peut le lire. L'origine qu'il nomme est imposée par le
navigateur au moyen de `frame-ancestors` : sur un autre site, le cadre ne montre rien, et l'élément garde son lien. Ouverte
directement, hors d'un cadre, la page est celle du site. Un jeton n'accorde rien de plus : il ne connecte personne, et la
page décide de ce qu'elle montre.

Un jeton est vérifié de nouveau à chaque requête : une origine que l'intégration n'autorise plus, une intégration retirée
de la configuration, un jeton expiré et une clé retirée sont refusés.
