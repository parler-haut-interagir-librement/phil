<!-- phil-dev source-sha256=12516d6749389fbe0a9665daea15703e12d5f0e12c9fb37b51aac3e06729cd2e -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilFormsBundle` | `final class`, `AbstractBundle` | Alias `phil_forms` ; `NOTIFIER_TAG` |
| `Domain\Model\Form`, `Entry` | entités | Un formulaire ; une entrée |
| `Domain\ValueObject\Field` | valeur | Un champ : son type, sa condition, ses paliers, ses choix disqualifiants, ses capacités, sa sensibilité, les âges d'une date de naissance ; `Field::age()` |
| `Domain\ValueObject\Notification` | valeur | Un courriel envoyé de chaque entrée sous une condition |
| `Domain\Model\ChoiceCount` | entité | Les places occupées dans un choix, pour le schéma |
| `Domain\Contract\ConditionEvaluator`, `FieldSealer`, `ChoicePlaces` | interfaces | Les conditions ; le scellement des valeurs sensibles ; les places dans les choix |
| `Domain\Logic\BasicConditions` | `final readonly class` | Les conditions `==`, `!=` et `in` sur un champ |
| `Domain\Field\FieldType` | enum | Ce qu'un champ accepte |
| `Domain\Repository\FormRepository` | interface | Enregistrer, trouver, lister, ce qui a expiré, les entrées qui concernent une personne |
| `Domain\Subject\EmailDigester` | interface | L'empreinte à clé d'une adresse |
| `Domain\Exception\FormsException`, `InvalidForm`, `InvalidEntry`, `UnreadableValue`, `UnsealedSensitiveField` | exceptions | Tout ce que le bundle lance ; `UnsealedSensitiveField` pour un formulaire qui a un champ sensible et aucun scelleur |
| `Application\Submissions` | `final readonly class` | Accepte une entrée et ses places, avertit les notificateurs |
| `Application\SealedValues` | `final readonly class` | Les valeurs d'une entrée, celles qui sont scellées étant ouvertes |
| `Application\Retention` | `final readonly class` | Supprime ce qui a expiré |
| `Application\HmacEmailDigester` | `final readonly class` | HMAC-SHA256 de l'adresse normalisée |
| `Application\CurrentSubject` | interface | L'identifiant de la personne qui envoie un formulaire |
| `Application\EntryNotifier`, `NotificationFailed` | interface, exception | Avertir quelqu'un d'une entrée |
| `Infrastructure\Doctrine\DoctrineFormRepository` | adaptateur | Doctrine ORM ; les places dans les choix |
| `Infrastructure\KeyManagement\KeyManagementFieldSealer` | adaptateur | Le scellement par le chiffrement d'enveloppe du composant KeyManagement de Symfony |
| `Infrastructure\DependencyInjection\FormsServices`, `FieldSealerPass` | câblage | Les services de la configuration ; le scelleur quand `sealing.key` nomme une clé maîtresse |
| `Infrastructure\Form\EntryType` | type de formulaire | Le formulaire Symfony d'une définition ; avec `phil/form-logic`, ses rangées conditionnelles nommées et le `phil_form_logic_steps` de sa vue |
| `Infrastructure\Form\LogicSteps` | `final readonly class` | Les conditions des champs qu'affiche une page, comme les étapes du contrôleur Stimulus de `phil/form-logic` |
| `Infrastructure\Http\FormController` | contrôleur | La page d'un formulaire |
| `Infrastructure\Mailer\EmailNotifier` | notificateur | Le courriel |
| `Infrastructure\Webhook\WebhookNotifier` | notificateur | Un webhook signé |
| `Infrastructure\Console\PurgeEntriesCommand` | commande de console | `phil:forms:purge` |
| `Infrastructure\Security\TokenCurrentSubject` | adaptateur | L'utilisateur connecté par Symfony Security |
| `Infrastructure\Privacy\FormEntriesPrivacy` | module de `phil/privacy-bundle` | Exporte et efface les entrées qui concernent une personne |
