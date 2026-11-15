<!-- phil-dev source-sha256=5d4e72ac4920b4e0b1484f59dd5a55b646c65241f207e784204f3108c4d2225c -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Brancher un fournisseur fédéré | Implémentez `Infrastructure\Federation\Contract\ExternalProvider` et étiquetez le service `phil_identity_provider.external_provider` : voir [Fournisseurs fédérés](external-providers.md) |
| Envoyer des messages texte par une autre passerelle | Implémentez `Application\Contract\SmsSender` et nommez le service dans `sms.sender` |
| Envoyer autrement les courriels | Donnez à `Application\Contract\AccountMailer` un alias vers votre propre service |
| Écrire les événements ailleurs | Implémentez `Application\Contract\AuditRecorder` et nommez le service dans `audit` |
| Sceller autrement les secrets | Donnez à `Application\Contract\SecretCipher` un alias vers votre propre service |
| Réagir à ce qui arrive aux comptes | Écoutez `Application\Event\IdentityEvent` sur le répartiteur d'événements : son type et ses identifiants |
| Garder les lignes ailleurs | Donnez à une interface de `Domain\Contract` — `AccountRepository`, `PasskeyRepository`, … — un alias vers votre propre magasin |
| Protéger vos propres actions | Exigez `PHIL_STEP_UP` avec `#[IsGranted]` ou `isGranted()` |
| Changer une page | Remplacez son gabarit sous `templates/bundles/PhilIdentityProviderBundle/`, ou nommez votre mise en page dans `layout` |
| Traduire les pages | Ajoutez `translations/phil_identity_provider.<locale>.php` (ou `.yaml`, `.xlf`) avec les clés du fichier anglais |
