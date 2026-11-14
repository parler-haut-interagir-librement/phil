<!-- phil-dev source-sha256=c457beb28507751a5b6e4e8cebacf7501b98a75ad64364b22335cdeeddd60e03 -->
# Page de vérification

La route `phil_open_badges_verify` de `config/verification_routes.php`, à `/verify` sous son préfixe, est une page où
n'importe qui vérifie une attestation :

- collée : un jeton compact, ou le JSON d'un document avec sa preuve ;
- ou intégrée à une image de badge, PNG ou SVG, fournie comme fichier ;
- avec, au choix, l'adresse de courriel ou l'URL de la personne à qui le visiteur s'attend à ce qu'elle ait été émise.

La page répond si l'attestation est valable et affiche le nom et la description de la réussite, l'émetteur, la période
de validité, la façon dont elle est sécurisée, son état, et si l'identité fournie est celle du destinataire. Elle
répond `200` quand l'attestation est valable, et `422` quand elle ne l'est pas — non vérifiée, révoquée ou suspendue,
ou émise à quelqu'un d'autre —, avec la raison.

| Vérification | Comment |
|---|---|
| La preuve | La clé de l'émetteur, ou une clé de `trusted_keys`, ou un `did:key` Ed25519, chacune seulement pour l'émetteur sous lequel elle est ; avec `remote_issuers` activé, la clé du profil ou du document `did:web` d'un autre émetteur, lue par `phil/safe-http` et mise en cache ([Autres émetteurs](remote-issuers.md)). Rien d'autre n'est récupéré. |
| La période | `validFrom` et `validUntil` au moment de l'horloge. |
| L'état | Lu dans les `StatusLists` de l'application quand la liste de l'entrée est l'une des siennes ; sinon, affiché comme non publié par le site. |
| Le destinataire | Le DID, ou l'empreinte salée de l'adresse de courriel ou de l'URL, comparée en temps constant. |

La page ne conserve rien, ne garde aucune session et ne pose aucun témoin ; ses réponses portent
`Cache-Control: no-store` et `Referrer-Policy: no-referrer`. Une image ou un texte de plus de
`verification.max_upload` octets est refusé avant d'être lu.

## Limiter les vérifications

Chaque vérification canonise un document dans les bornes de `phil/open-badges`, ce qui coûte plus qu'une page, et peut
lire le profil d'un autre émetteur quand `remote_issuers` est activé. Avec
`phil/rate-limit-bundle` enregistré, `verification.rate_limit` nomme l'un de ses limiteurs, et chaque vérification (un
`POST` vers la page) y est comptée; afficher la page ne l'est pas.

```yaml
# config/packages/phil_rate_limit.yaml
phil_rate_limit:
    cache_pool: cache.rate_limits
    limiters:
        badge_checks: { policy: sliding_window, limit: 20, interval: '1 minute', key: ip }

# config/packages/phil_open_badges.yaml
phil_open_badges:
    verification:
        rate_limit: badge_checks
```

Une vérification au-delà de la limite reçoit `429 Too Many Requests` de `phil/rate-limit-bundle`, avec `Retry-After` et
sa propre page. Un limiteur que ce bundle ne déclare pas arrête la construction, tout comme une limite sans ce bundle.
Une règle que la route porte déjà dans sa valeur par défaut `_rate_limit` est gardée telle quelle, et la configuration
est alors laissée de côté.

## Mise en page et traductions

La page étend `verification.layout`, qui définit les blocs `title` et `content` ; la valeur par défaut est une page
minimale propre au bundle. Ses textes sont dans le domaine de traduction `phil_open_badges`, en anglais et en
français ; les raisons du refus d'une attestation viennent de la bibliothèque, en anglais.

Pour modifier la page elle-même, remplacez `@PhilOpenBadges/verify.html.twig` dans
`templates/bundles/PhilOpenBadgesBundle/` ; elle reçoit `layout` et `result`, un `CheckResult`, ou null avant une
vérification.
