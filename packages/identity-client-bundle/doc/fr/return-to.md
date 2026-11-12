<!-- phil-dev source-sha256=1711dbcb45b0bf0b813630f4d85c2cf8ad3fb4d26a286b2e883c13c9fbf1e834 -->
# Retour

Un lien vers le chemin de départ peut dire où la connexion doit aboutir :

```twig
<a href="{{ path('_oidc_login_start_main', {return_to: app.request.requestUri}) }}">Sign in</a>
```

Le paramètre reste dans l'application. Il est lu quand la requête d'autorisation est construite, sa valeur validée va
dans la session, et le fournisseur ne le voit jamais.

## Ce qui est accepté

`DeclaredOriginsReturnToPolicy` accepte :

- un chemin de l'application, comme `/account?tab=a` ; le fragment est retiré ;
- une adresse sur une origine listée dans `return_to.origins`.

Une origine est un simple `https://host[:port]`, ou `http` sur un hôte de bouclage. Le candidat est lu avec l'analyseur
WHATWG, celui que suivent les navigateurs, et la réponse est reconstruite à partir de l'adresse analysée. Tout le reste
est écarté : barres obliques inverses, caractères de contrôle, identifiants de connexion intégrés, adresses relatives au
schéma, chemins qui se normalisent en `//host`, et valeurs de plus de 2048 octets.

## Ce qui arrive en cas de refus

Le candidat est retiré sans erreur, une cible mémorisée plus tôt est oubliée, et la connexion aboutit à
`paths.landing`. Un événement `ReturnToDiscarded` le signale, sans la valeur refusée.

Symfony applique une vérification de plus quand il redirige : une adresse absolue doit être sur l'hôte de la requête ou
dans le domaine du témoin de session, sinon le navigateur va à `/`. Une origine que la session n'atteint pas ne
trouverait de toute façon pas l'utilisateur connecté.

Une page protégée atteinte sans être connecté n'a besoin d'aucun paramètre : le pare-feu la mémorise comme d'habitude.
