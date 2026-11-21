<!-- phil-dev source-sha256=a2dca47c3a70a2f833bdd79bb1242abb4f7bdf055f645914bdf4e6e4882c5f8a -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Brancher un fournisseur fédéré | Implémentez `Infrastructure\Federation\Contract\ExternalProvider` et étiquetez le service `phil_identity_provider.external_provider` : voir [Fournisseurs fédérés](external-providers.md) |
| Ajouter des revendications à userinfo | Implémentez `Application\Contract\ClaimsProvider` : autoconfiguré, ou étiquetez le service `phil_identity_provider.claims_provider` ; voir [Portées et revendications](oidc-provider.md#portées-et-revendications) |
| Donner aux comptes vos propres identifiants | Implémentez `Application\Contract\AccountIdGenerator` et nommez le service dans `accounts.id_generator` : voir [Identifiants](accounts.md#identifiants) |
| Comparer les adresses selon les règles d'un fournisseur de courriel | Implémentez `Application\Contract\AddressCanonicalizer` et nommez le service dans `accounts.canonicalizer` : voir [Adresses](accounts.md#adresses) |
| Signer avec un module de sécurité matériel ou un service de gestion de clés | Implémentez `Application\Contract\SigningKeySource`, ses signeurs `Application\Contract\Signer`, et nommez le service dans `oidc.signing_keys.service` : voir [Un module de sécurité matériel](oidc-provider.md#un-module-de-sécurité-matériel) |
| Dire autrement aux clients qu'une session a pris fin | Donnez à `Application\Contract\LogoutNotifier` un alias vers votre propre service |
| Envoyer des messages texte par une autre passerelle | Implémentez `Application\Contract\SmsSender` et nommez le service dans `sms.sender` ; il envoie sur-le-champ, ou met en file des identifiants seulement, jamais le code |
| Mener les liens des courriels vers l'hôte de chaque locataire | Donnez à `Application\Contract\LinkOrigin` un alias vers votre propre service : il génère chaque lien sur l'hôte qu'il choisit |
| Envoyer autrement les courriels | Donnez à `Application\Contract\AccountMailer` un alias vers votre propre service ; il envoie sur-le-champ, ou met en file des identifiants seulement — le compte et le genre de lien —, jamais le lien (voir [Pourquoi les courriels de compte et les SMS ne passent jamais par Messenger](links-and-sms.md#pourquoi-les-courriels-de-compte-et-les-sms-ne-passent-jamais-par-messenger)) |
| Écrire les événements ailleurs | Implémentez `Application\Contract\AuditRecorder` et nommez le service dans `audit` |
| Sceller autrement les secrets | Donnez à `Application\Contract\SecretCipher` un alias vers votre propre service |
| Réagir à ce qui arrive aux comptes | Écoutez `Application\Event\IdentityEvent` sur le répartiteur d'événements : son type et ses identifiants |
| Garder les lignes ailleurs | Donnez à une interface de `Domain\Contract` — `AccountRepository`, `AccountAddressRepository`, `LinkTokenRepository`, `PasskeyRepository`, … — un alias vers votre propre magasin ; `LinkTokenRepository::consume()` doit retirer le jeton par une seule écriture conditionnelle ; `GrantRepository::consumeCode()` et `rotateToken()` doivent changer la ligne par une seule écriture conditionnelle, `revokeFamily()` doit écrire la marque de la famille avant de révoquer les jetons, et `isFamilyRevoked()` doit lire la marque telle qu'écrite, non depuis un cache, et `SecondFactorRepository::apply()` et `AccountRepository::apply()` doivent relire la ligne et la retenir pendant qu'elle change, de sorte qu'un code, un lien de réinitialisation ou un échec de connexion ne compte qu'une fois même avec deux requêtes simultanées ; donnez à `Application\Contract\Transactions` un alias vers une transaction de votre magasin, pour que fermer une session et révoquer ses jetons aillent ensemble |
| Protéger vos propres actions | Exigez `PHIL_STEP_UP` avec `#[IsGranted]` ou `isGranted()` |
| Changer une page | Remplacez son gabarit sous `templates/bundles/PhilIdentityProviderBundle/`, ou nommez votre mise en page dans `layout` |
| Traduire les pages | Ajoutez `translations/phil_identity_provider.<locale>.php` (ou `.yaml`, `.xlf`) avec les clés du fichier anglais |
