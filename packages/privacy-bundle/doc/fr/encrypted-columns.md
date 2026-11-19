<!-- phil-dev source-sha256=3ff65abe216d2a2a00a793585d92ce059c3c4cd9738de36da86b83600dc1fda6 -->
# Chiffrer les données personnelles au repos

Le bundle déclare, exporte, efface et anonymise les données personnelles ; il ne les chiffre pas. Les colonnes de
données personnelles sont chiffrées au repos par le composant KeyManagement de Symfony, avec deux attributs de son pont
Doctrine ORM, `symfony/doctrine-orm-key-management` : `#[Encrypted]` sur la colonne d'une propriété, et
`#[BlindIndexed]` sur une colonne sœur qui en garde une trace sur laquelle on peut chercher. Les deux sont
expérimentaux dans Symfony 8.2, hors de la promesse de rétrocompatibilité de Symfony.

## Les colonnes à chiffrer

Chiffrez les colonnes dont la fuite nuirait à elle seule à la personne, et que l'application lit par ligne plutôt
qu'elle ne les cherche ou ne les trie :

| À chiffrer | À laisser en clair |
|---|---|
| Numéros de téléphone, adresses postales, dates de naissance | Identifiants, clés étrangères, dates de création et de mise à jour |
| Renseignements de santé, d'alimentation, d'accessibilité et de personne à joindre en cas d'urgence | Les dates et les catégories que lit le calendrier de conservation |
| Texte libre qu'une personne a écrit sur elle-même : notes, réponses à des questions | Les colonnes triées, regroupées ou comparées en SQL |
| Identifiants gouvernementaux, quand l'application doit vraiment les garder | Tout ce qu'un anonymiseur `#[PersonalData]` doit encore réécrire en SQL |

Une adresse de courriel par laquelle l'application retrouve les gens est chiffrée, et un index aveugle à côté sert à la
recherche. Le chiffrement protège la base de données, ses sauvegardes et ses réplicas de qui les lit sans les clés ; il
ne protège pas les données de l'application elle-même, qui peut déchiffrer toute ligne qu'elle peut charger.

`#[Encrypted]` et `#[PersonalData]` vont ensemble sur la même propriété : le premier dit comment la valeur est
enregistrée, le second ce qu'elle est, pour le registre, l'export, l'effacement et l'anonymisation.

```php
use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Entity]
class Member
{
    #[ORM\Column(length: 180)]
    #[Encrypted(key: 'member.email')]
    #[PersonalData('email', retention: 'R-002')]
    private string $email = '';

    #[ORM\Column(length: 64)]
    #[BlindIndexed('email', 'member-email')]
    private string $emailIndex = '';

    #[ORM\Column(nullable: true)]
    #[Encrypted(key: 'member.contact')]
    #[PersonalData('phone')]
    private ?string $phone = null;
}
```

## Les colonnes chiffrées

`#[Encrypted]` chiffre la colonne d'une propriété par chiffrement d'enveloppe sous une clé maîtresse du KMS : la colonne
garde le type Doctrine de son mappage, qui convertit d'abord la valeur, et elle est enregistrée dans une colonne
binaire. La valeur est authentifiée avec la clé et le nom du type : une valeur copiée dans une colonne d'une autre clé
ne se déchiffre pas. Donnez à chaque sorte de données sa propre clé : des colonnes qui partagent une clé et un type
lisent les valeurs les unes des autres.

Avec un magasin de clés de données (`key_management.store`), la clé peut être omise : chaque colonne physique reçoit
alors sa propre portée de clé de données, dérivée des noms de son schéma, de sa table et de sa colonne.

Le KMS est un client de `key_management.clients` : celui de l'application — AWS KMS, Azure Key Vault, Google Cloud KMS,
HashiCorp Vault Transit — ou un client `sodium://` local dont les clés maîtresses sont 32 octets aléatoires en
base64url :

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[member.email]=%env(MEMBER_EMAIL_KEY)%&keys[member.contact]=%env(MEMBER_CONTACT_KEY)%'
```

## Les index aveugles pour la recherche

Le chiffrement est aléatoire : `WHERE email = ?` ne correspond jamais à une colonne chiffrée. `#[BlindIndexed]` remplit
une colonne sœur à chaque flush avec une empreinte à clé de la valeur, égale pour des valeurs égales, et l'application
retrouve la ligne par cette empreinte :

```php
$repository->findOneBy(['emailIndex' => $memberEmailIndex->of($email)]);
```

L'index est un service `Symfony\Component\KeyManagement\BlindIndex` marqué `key_management.blind_index` avec son nom,
`member-email` ici, sur une clé de données créée une seule fois avec `key-management:generate-data-key` et gardée
enveloppée dans la configuration. Sa projection dit ce qui de la valeur est indexé : `Projection\Email` normalise une
adresse, `Projection\EmailDomain` en garde le domaine, `Projection\Verbatim` ne normalise rien, pour un numéro de
téléphone déjà écrit en E.164 par exemple.

Un index aveugle dit à quiconque lit la table quelles lignes partagent une valeur et combien de fois : n'indexez que ce
qui se cherche par égalité et a beaucoup de valeurs distinctes, comme une adresse de courriel, jamais une date de
naissance ni un code postal. L'écouteur n'écrit l'étiquette que par l'ORM : une ligne insérée par DBAL ou par un
`UPDATE` en lot garde une étiquette périmée.

## La rotation

Les clés font leur rotation dans le KMS et dans `symfony/key-management`, pas dans ce bundle, et aucune commande de ce
bundle ne chiffre de nouveau les valeurs déjà écrites.

Avec un magasin de clés de données, les valeurs des colonnes renvoient à des clés de données enregistrées :

- `rotate($scope)` du magasin, `RewrappableDataKeyStoreInterface`, retire la clé de données courante d'une portée et en
  crée une neuve : les valeurs écrites dès lors sont scellées sous elle, et celles déjà écrites continuent de renvoyer à
  la clé retirée, qui reste dans le magasin et ne doit pas être supprimée tant qu'elles demeurent.
- `key-management:rewrap-data-keys` enveloppe de nouveau les clés de données enregistrées sous un autre client ou une
  autre clé maîtresse, sans lire ni réécrire une valeur de colonne. L'ancienne clé maîtresse peut quitter le KMS une fois
  toutes les clés enregistrées enveloppées de nouveau.

Sans magasin, chaque valeur porte sa clé de données enveloppée : gardez l'ancienne clé maîtresse dans le KMS tant que
des valeurs scellées sous elle demeurent. Charger et enregistrer les entités ne rechiffre rien : l'unité de travail de
Doctrine compare les valeurs déchiffrées, les trouve égales et n'écrit aucune ligne, de sorte qu'une clé maîtresse
retirée ensuite détruit chaque valeur encore scellée sous elle.

La rotation limite ce que lit une clé de données divulguée, pas ce que lit l'accès au KMS : quiconque peut appeler le KMS
avec les identifiants de l'application déballe chaque clé de données, retirée ou courante, et déchiffre donc chaque
colonne. Restreignez cet accès à l'application, et auditez ses appels dans le KMS.

La clé de données d'un index aveugle ne fait jamais sa rotation : toute étiquette déjà écrite en est dérivée.

## L'effacement et l'anonymisation

Une colonne chiffrée s'efface et s'anonymise comme toute autre : les effaceurs et les anonymiseurs du bundle passent par
les entités, et l'ORM chiffre ce qu'ils écrivent. Une anonymisation en SQL qui contourne l'ORM écrit du texte en clair
dans une colonne chiffrée, qui ne se déchiffre plus ; anonymisez ces colonnes par les entités, ou mettez-les à `NULL`.
