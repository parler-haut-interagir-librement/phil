<!-- phil-dev source-sha256=ebd30f7efe0003b7f2d32790f2bf106e67c927284cbd6ec5f0312192039f9c33 -->
# Redirections externes

Une réponse qui envoie le navigateur vers un autre hôte est arrêtée sauf si cet hôte est permis : une adresse prise dans
la requête ne peut pas devenir une redirection ouverte.

```yaml
phil_browser_security:
    external_redirects:
        enabled: true
        action: refuse         # refuse, fallback or log
        fallback: /            # the path a refused redirect goes to with "fallback"
        allow:
            - pay.example.com  # one host
            - .example.net     # that domain's subdomains
```

| Action | Effet |
|---|---|
| `refuse` | La réponse devient `403 Forbidden`. |
| `fallback` | La réponse devient une redirection vers `fallback`, un chemin du site. |
| `log` | La réponse sort, et un avertissement est journalisé. |

La cible est résolue par `Uri\WhatWg\Url` comme un navigateur la résout, donc `//evil.example`, `/\evil.example` et une
tabulation égarée sont vus pour ce qu'ils sont. Seules les cibles `http` et `https` peuvent passer ; le même hôte que la
requête passe toujours. Une page de paiement hébergée ou un fournisseur d'identité va dans `allow`, ou dans la route qui
y redirige. Seul l'hôte est journalisé : une adresse peut porter un jeton.

## Ce que Symfony fait déjà

`HttpUtils` garde sur le site le chemin cible d'une connexion avec son motif `domain`, et rien d'autre dans Symfony ne
regarde un en-tête `Location`.

## Une page

```php
use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;

#[BrowserSecurity(add: ['external-redirects' => ['checkout.example.org']])]
public function pay(): Response
```

`remove: ['external-redirects']` désactive la vérification pour cette page.
