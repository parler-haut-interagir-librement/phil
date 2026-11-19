<!-- phil-dev source-sha256=50fcf8eb2bf9a14be6612c00e83f2b9552204dc6fab0537204495d152835d617 -->
# Liens de connexion et numéros de téléphone

## Liens de connexion

Le module `login_link` offre `/login/link` : l'adresse saisie, un lien de l'authentificateur `login_link` de Symfony
Security qui lui est envoyé par le composant Mailer, et la même réponse que l'adresse ait un compte ou non. Le
pare-feu configure l'authentificateur, sa route de vérification `phil_identity_provider_login_link_check`, sa durée de
vie, ses utilisations et les propriétés sur lesquelles il signe — `userIdentifier` et `passwordChangedAt` empêchent
un lien de survivre à un changement de mot de passe :

```yaml
login_link:
    check_route: phil_identity_provider_login_link_check
    signature_properties: [userIdentifier, passwordChangedAt]
    lifetime: 600
    max_uses: 1
```

Le lien est généré sur `public_origin`, jamais sur l'hôte que la requête a nommé : voir
[Où mènent les liens](accounts.md#où-mènent-les-liens).

Un lien de connexion n'indique aucune méthode : il compte pour `IS_AUTHENTICATED_RECENTLY`, non pour `PHIL_STEP_UP`
d'un compte qui a une méthode plus forte, et un compte qui a une application d'authentification se voit demander son
code ensuite.

## Numéros de téléphone

Le module `sms` vérifie un numéro de téléphone : `/account/phone` prend un numéro au format E.164 — les espaces, les
tirets, les points et les parenthèses sont retirés —, envoie six chiffres par le port `SmsSender`, et vérifie le
numéro quand le code revient. Le code est conservé haché dans la session avec le numéro, une expiration de
`sms.lifetime` secondes et `sms.attempts` essais. Le `SmsSender` par défaut est le texteur du composant Notifier,
vers le transport SMS que l'application a configuré ; `sms.sender` nomme plutôt un service à elle. Le débit d'envoi
relève du limiteur de débit de l'application placé devant la route.

Un numéro vérifié permet à l'application de joindre la personne, et figure dans les revendications de la portée
`phone`. Les messages texte comme deuxième facteur sont prévus, non implémentés : un SMS peut être intercepté, et
l'application d'authentification ou une clé d'accès est le deuxième facteur que le bundle offre.
