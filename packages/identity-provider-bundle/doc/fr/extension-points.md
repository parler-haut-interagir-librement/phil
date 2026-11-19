<!-- phil-dev source-sha256=e6d4bac54978afdf43af022b53d08cd3e664f98d82463082a787eea534d2c2e8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Brancher un fournisseur fédéré | Implémentez `Infrastructure\Federation\Contract\ExternalProvider` et étiquetez le service `phil_identity_provider.external_provider` : voir [Fournisseurs fédérés](external-providers.md) |
| Envoyer des messages texte par une autre passerelle | Implémentez `Application\Contract\SmsSender` et nommez le service dans `sms.sender` |
| Mener les liens des courriels vers l'hôte de chaque locataire | Donnez à `Application\Contract\LinkOrigin` un alias vers votre propre service : il génère chaque lien sur l'hôte qu'il choisit |
| Envoyer autrement les courriels | Donnez à `Application\Contract\AccountMailer` un alias vers votre propre service |
| Écrire les événements ailleurs | Implémentez `Application\Contract\AuditRecorder` et nommez le service dans `audit` |
| Sceller autrement les secrets | Donnez à `Application\Contract\SecretCipher` un alias vers votre propre service |
| Réagir à ce qui arrive aux comptes | Écoutez `Application\Event\IdentityEvent` sur le répartiteur d'événements : son type et ses identifiants |
| Garder les lignes ailleurs | Donnez à une interface de `Domain\Contract` — `AccountRepository`, `PasskeyRepository`, … — un alias vers votre propre magasin ; `GrantRepository::consumeCode()` et `rotateToken()` doivent changer la ligne par une seule écriture conditionnelle, et `SecondFactorRepository::apply()` et `AccountRepository::apply()` doivent relire la ligne et la retenir pendant qu'elle change, de sorte qu'un code, un lien de réinitialisation ou un échec de connexion ne compte qu'une fois même avec deux requêtes simultanées ; donnez à `Application\Contract\Transactions` un alias vers une transaction de votre magasin, pour que fermer une session et révoquer ses jetons aillent ensemble |
| Protéger vos propres actions | Exigez `PHIL_STEP_UP` avec `#[IsGranted]` ou `isGranted()` |
| Changer une page | Remplacez son gabarit sous `templates/bundles/PhilIdentityProviderBundle/`, ou nommez votre mise en page dans `layout` |
| Traduire les pages | Ajoutez `translations/phil_identity_provider.<locale>.php` (ou `.yaml`, `.xlf`) avec les clés du fichier anglais |
