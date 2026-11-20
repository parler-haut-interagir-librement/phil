<!-- phil-dev source-sha256=f88e40cc5d6b0d725edaed00aa2379a2a4fa007b40fe235415a095a5eab0e23d -->
# Correspondance avec ASVS 5.0

Les exigences d'[OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) que le bundle satisfait, avec le
test qui montre chacune, et celles qu'il laisse à l'application. Le guide du monodépôt,
[ASVS 5.0 : ce que l'application garde](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md),
rassemble ce que chaque paquet laisse.

## Satisfaites par le bundle

| Exigence | Niveau | Comment | Démontrée par |
|---|---|---|---|
| 3.4.1 HSTS sur chaque réponse, un an ou plus, sous-domaines dès le niveau 2 | 1 | `Strict-Transport-Security: max-age=31536000; includeSubDomains` par défaut ; un `max_age` plus court pour un déploiement progressif est envoyé tel que configuré, jamais avec `preload` | `PhilBrowserSecurityBundleTest::testStrictTransportSecurityLastsAYearByDefault` |
| 3.4.3 Une politique avec `object-src 'none'` et `base-uri 'none'`, une liste d'autorisation ou des nonces | 2 | La politique par défaut, envoyée entière sur chaque page ; des nonces par réponse avec `csp_nonce()` | `HeaderBuilderTest::testTheDefaultPolicyIsSentWhole` |
| 3.4.4 `X-Content-Type-Options: nosniff` sur chaque réponse | 2 | Un en-tête de chaque réponse | `PhilBrowserSecurityBundleTest`, les en-têtes par défaut |
| 3.4.5 Une politique de référent | 2 | `Referrer-Policy: strict-origin-when-cross-origin` | `PhilBrowserSecurityBundleTest`, les en-têtes par défaut |
| 3.4.6 `frame-ancestors` sur chaque réponse | 2 | `frame-ancestors 'self'` dans la politique par défaut, à côté de `X-Frame-Options` | `HeaderBuilderTest::testTheDefaultPolicyIsSentWhole` |
| 3.4.7 Un endroit où signaler les violations | 3 | `report-uri` et `report-to`, vers la route du bundle ou vers `public_origin` | `BrowserSecurityBundleTest::testAForgedHostNeverChoosesWhereTheReportsGo` |
| 3.2.1 Contenu non rendu dans le mauvais contexte | 1 | Les en-têtes du contenu d'utilisateur : une politique en bac à sable, `attachment` pour tout type qui peut s'exécuter | [Contenu d'utilisateur](user-content.md) et ses tests |
| 5.4.1, 5.4.2 Un nom de fichier que le serveur choisit, encodé | 2 | Le nom donné aux en-têtes de contenu d'utilisateur, débarrassé des chemins, des caractères de contrôle et des guillemets, écrit avec `filename*` | [Contenu d'utilisateur](user-content.md) et ses tests |
| 3.7.2 Redirections vers un autre hôte seulement sur une liste d'autorisation | 2 | La vérification des redirections externes, ses hôtes permis et ses trois actions | [Redirections externes](redirects.md) et ses tests |
| 4.1.2 Seules les pages sont redirigées de HTTP vers HTTPS | 2 | La redirection par route, `exclude_paths` et le remplacement `https-redirect` | [HTTPS](https.md) et ses tests |

## Laissées à l'application

| Exigence | Niveau | Pourquoi elle revient à l'application |
|---|---|---|
| 3.1.1 Les fonctionnalités du navigateur qu'attend l'application, documentées | 3 | Sa propre documentation ; les [en-têtes](headers.md) de ce bundle listent ce qu'il envoie |
| 3.3.1 à 3.3.4 Témoins `Secure`, `SameSite`, `__Host-`, `HttpOnly` | 1, 2 | Réglés par `framework.session` et par chaque témoin que l'application écrit ; les [témoins signés](cookies.md) signent des valeurs, ils ne règlent pas d'attributs |
| 3.4.2 Un `Access-Control-Allow-Origin` fixe ou sur liste d'autorisation | 1 | La configuration CORS de l'application, comme un bundle CORS |
| 3.4.8 `Cross-Origin-Opener-Policy` sur les documents | 3 | Offert sous `headers.cross_origin_opener_policy`, désactivé par défaut : une page qui ouvre des fenêtres de paiement ou de connexion doit d'abord être mise à l'essai |
| 3.5.3, 3.5.8 Méthodes sûres et vérifications de `Sec-Fetch-*` | 1, 3 | Les routes et les contrôleurs ; le bundle envoie des en-têtes et n'en lit aucun de la requête |
| 3.6.1 Subresource Integrity pour les ressources externes | 3 | Le pipeline de ressources ; le bundle nomme des empreintes dans la politique, il ne les calcule pas pour les fichiers de tiers |
| 3.7.5 Comportement sur un navigateur sans les fonctionnalités attendues | 3 | Une décision de produit |
