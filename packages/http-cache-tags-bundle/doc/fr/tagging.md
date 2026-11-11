<!-- phil-dev source-sha256=e2acbcfa16dd55f1ed604c06af2b3e6908769fa0ad417be42f790c606192d287 -->
# Étiquetage

Une étiquette compte de 1 à 128 lettres, chiffres, `_`, `.`, `:` et `-` : `page:42`, `product-list`.

| Depuis | Comment |
|---|---|
| Un contrôleur | `#[CacheTag('page:{id}', 'menu')]` sur la méthode ou la classe, répétable ; `{name}` est l'attribut de la requête (un paramètre de route), et une étiquette dont l'attribut manque, ou dont la valeur la rend invalide, est omise |
| Un gabarit | `{{ cache_tag('product:' ~ product.id) }}`, qui n'écrit rien |
| Le code | `ResponseTags::add('page:42')` |

Quand la réponse principale part, ses étiquettes sont écrites dans l'en-tête configuré, chacune une seule fois, dans
l'ordre d'arrivée (`xkey: page:42 menu`, ou `Cache-Tag: page:42,menu`), puis oubliées pour la requête suivante.
L'en-tête est écrit tard, après le `SessionListener` de Symfony et avant son `ProfilerListener` : un écouteur
`kernel.response` de l'application qui s'exécute avant l'écouteur de session peut donc encore ajouter des étiquettes.
Les étiquettes d'une sous-requête restent pour la réponse principale, pour qu'un fragment étiquette la page dont il fait
partie. Une réponse sans étiquette ne reçoit pas d'en-tête.

Un paramètre de route vient de l'URL : `GET /page/café` donne à `{slug}` la valeur `café`, et `page:café` n'est pas une
étiquette valide. Une telle étiquette est omise plutôt que transformée en erreur, et la page est donc servie sans elle.
Étiquetez une page dont le slug peut contenir des accents ou des espaces par son identifiant, `page:{id}`, qui est aussi
l'étiquette que donne un `CacheTagResolver` quand la page change. Une étiquette écrite sans paramètre substituable
relève du développeur, et une étiquette invalide est une `InvalidCacheTag`.

Le cache placé devant garde la réponse selon ses propres règles : donnez-lui un `Cache-Control: public, s-maxage=…`.

L'en-tête nomme les identifiants de ce que montre la page — `user:4812`, `order:99173` — et le bundle l'écrit sur
chaque réponse étiquetée : le cache placé devant doit le retirer avant que la réponse n'atteigne le client. Le module
xkey de Varnish le garde, donc le VCL finit `vcl_deliver` par `unset resp.http.xkey;` ; un CDN qui lit `Cache-Tag` ou
`Surrogate-Key` le retire d'habitude, mais vérifiez-le. Gardez peu d'étiquettes par réponse — une liste s'étiquette
`product-list` plutôt que chacune de ses entités —, car un en-tête de plus de 8 Ko environ fait répondre nginx ou
Varnish par une erreur 502.
