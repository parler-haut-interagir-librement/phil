<!-- phil-dev source-sha256=556558c77d25e921dab677d3b68f792f9708ffc582ecbd6cbc940e0ec8927af1 -->
# Entrées et conservation

## Ce qui est conservé

Une `Entry` contient les valeurs, une par champ dans l'ordre du formulaire, sa date d'arrivée, sa langue, sa date de
suppression et ce qui nomme la personne qu'elle concerne ([ci-dessous](#qui-une-entrée-concerne)). **Rien d'autre**
n'est conservé au sujet du visiteur : ni adresse IP, ni navigateur, ni témoin de connexion. La protection contre le
pourriel lit le client pour limiter son débit, et ne conserve rien non plus.

```php
$repository->entries($form, limit: 50, offset: 0);   // newest first
$repository->entry($id);
```

## Qui une entrée concerne

Une demande d'accès ou d'effacement nomme une personne ; ses entrées doivent être trouvées sans les lire toutes. Une
entrée conserve l'une de deux choses, ou les deux :

- **L'identifiant de la personne**, quand elle était connue au moment de l'envoi. `CurrentSubject::current()` (par
  défaut l'utilisateur connecté par Symfony Security, selon `getUserIdentifier()`) est passé à `Submissions::submit()`
  et conservé dans `subject`, sous la forme que produit `Entry::subjectKey()`. Il est gardé tel quel s'il compte de 1 à
  255 octets sans espace ni caractère de contrôle, comme le `DataSubject` de `phil/privacy-bundle` ; sinon
  (`Jean Dupont`, un identifiant plus long), il est gardé sous la forme `sha256:` suivie de son SHA-256, pour qu'une
  telle personne puisse quand même envoyer le formulaire. Une demande la nomme alors par cette clé,
  `DataSubject::fromString(Entry::subjectKey('Jean Dupont'))`. Une application dont les demandes nomment les personnes
  autrement crée un alias de `CurrentSubject` vers son propre service.
- **L'empreinte d'une adresse**, pour un visiteur. Un formulaire désigne l'un de ses champs de courriel
  (`Form::identifyBy('email')`), et l'entrée conserve `email_digest`, le HMAC-SHA256 de l'adresse sans espaces aux
  extrémités et en minuscules, sous une clé dérivée de `phil_forms.digest_key` par HKDF-SHA256 pour cet usage seul
  (`phil_forms.email_digest`), de sorte que les empreintes ne correspondent jamais aux pseudonymes qu'un autre bundle
  garde pour l'adresse. L'adresse est trouvée quelle que soit la façon dont
  elle a été écrite, et ne peut pas être essayée contre les empreintes sans la clé. **Changer la clé** rend les entrées
  déjà conservées introuvables pour les demandes par adresse, jusqu'à la fin de leur conservation.

```php
$repository->about('member-7', $digester->digest('ada@example.com'));   // oldest first
```

Avec `phil/privacy-bundle`, `FormEntriesPrivacy` est le module `forms` de ses réponses d'accès et d'effacement : le
sujet de la demande est cherché à la fois comme identifiant et comme adresse. L'exportation donne le formulaire, les
dates, la langue et les valeurs de chaque entrée. L'effacement supprime les entrées et déclare le stockage
`phil_form_entry` comme `deleted`, avec le même rapport à une deuxième exécution. Les courriels et les webhooks déjà
envoyés sont hors de la portée du bundle.

## Valeurs sensibles

Les valeurs des champs sensibles sont scellées avant que l'entrée soit conservée. Le scelleur par défaut est
`KeyManagementFieldSealer`, câblé quand `sealing.key` nomme une clé maîtresse : il utilise le chiffrement d'enveloppe du
composant KeyManagement de Symfony, et le bundle n'a aucune cryptographie à lui : une clé de données neuve, enveloppée
par cette clé maîtresse du KMS (le client par défaut du composant, ou le client que nomme `sealing.kms`), la valeur
chiffrée localement avec AES-256-GCM, l'id de l'entrée et le nom du champ étant authentifiés avec elle, de sorte
qu'une valeur scellée copiée dans une autre entrée ou un autre champ ne s'ouvre pas. L'entrée nomme les champs scellés
dans `sealed` ; une valeur scellée se lit `phs1.<key>.<envelope>`, le nom de la clé maîtresse et l'enveloppe en
base64url, le premier octet de l'enveloppe, 2, nommant son format, de sorte qu'elle s'ouvre sous la clé avec laquelle
elle a été scellée après que `sealing.key` change.

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[forms2026]=%env(FORMS_SEALING_KEY)%'

# config/packages/phil_forms.yaml
phil_forms:
    sealing:
        key: forms2026
```

La clé maîtresse d'un client `sodium://` local est faite de 32 octets aléatoires en base64url, gardés dans une variable
d'environnement ou dans le coffre des secrets de Symfony ; une application qui a son propre KMS — AWS, Azure, Google
Cloud, Vault — déclare plutôt ce client et nomme l'une de ses clés maîtresses. Avec `sealing.key` définie et sans le
composant KeyManagement, ou sans client, le conteneur s'arrête avec un message qui dit quoi changer.

```php
$values = $sealedValues->reveal($entry);   // SealedValues: the values as they were sent
```

`SealedValues::reveal()` lève `UnreadableValue` quand une valeur ne s'ouvre pas, ou quand aucun scelleur n'est
installé. La réponse d'accès de `phil/privacy-bundle` donne les valeurs ouvertes ; une valeur qui ne s'ouvre pas est
omise, et son champ est listé sous `unreadable`.

Changer `sealing.key` scelle les nouvelles valeurs sous la nouvelle clé maîtresse et ne scelle pas de nouveau les
entrées conservées : une ancienne clé maîtresse reste dans le KMS jusqu'à ce que les entrées scellées sous elle soient
purgées. Tant que le KMS ne détient pas la clé maîtresse que nomme `sealing.key`, le scellement lève la
`KeyNotFoundException` du composant et l'entrée n'est pas conservée.

### Conserver les valeurs sensibles telles qu'envoyées

Sans `sealing.key`, et sans `FieldSealer` de l'application, aucun scelleur n'est configuré : un formulaire qui a un champ
sensible est refusé avec `UnsealedSensitiveField`, sa page n'étant pas construite et aucune entrée n'étant prise, de
sorte qu'une valeur que le formulaire dit sensible ne repose jamais en clair dans la base de données par erreur. Une
application qui accepte ce risque le dit :

```yaml
# config/packages/phil_forms.yaml
phil_forms:
    allow_unsealed_sensitive: true
```

> **Avertissement :** avec cette option, les valeurs des champs sensibles sont conservées telles qu'envoyées, lisibles
> par quiconque lit la base de données, ses sauvegardes ou ses réplicas. Ne l'activez que pour un environnement de
> développement, ou là où la base de données elle-même est chiffrée et son accès contrôlé à cette fin ; préférez un
> client `sodium://` local du composant KeyManagement, qui coûte une variable d'environnement.

Les valeurs sensibles ne sont jamais envoyées par courriel ni postées, scellées ou non.

## Dates

Les dates d'une entrée sont conservées en UTC, et `submittedAt()` et `purgeAfter()` les donnent en UTC, quel que soit le
fuseau horaire par défaut de PHP : une exportation montre le moment où l'entrée est arrivée, pas cette heure d'horloge
dans le fuseau de l'application.

## Pendant combien de temps

Chaque formulaire conserve ses entrées pendant un nombre de jours, de 1 à 3650, 365 par défaut : le temps dont leur
finalité a besoin, comme le demandent la Loi 25 du Québec et le RGPD, et pas plus. La date de suppression d'une entrée
est fixée à son arrivée : raccourcir la conservation d'un formulaire laisse donc les entrées déjà conservées telles
quelles.

## Suppression

```sh
bin/console phil:forms:purge --batch=500
```

La commande supprime les entrées dont le temps est écoulé, un lot à la fois, jusqu'à ce qu'il n'en reste aucune ;
exécutez-la chaque nuit par cron ou par un planificateur. `Retention::purge($limit)` fait la même chose depuis le code.
Retirer un formulaire par le dépôt retire avec lui ses entrées et ses décomptes de places ; une entrée retirée rend
les places qu'elle occupait.
