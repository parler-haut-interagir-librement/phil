<!-- phil-dev source-sha256=05c3718764db5b6ebf7b166b3f873bc48e568907f28632eaf7a5d30e1d81860c -->
# Chiffrement au repos

## Ce qui est scellé

Le sujet de chaque conversation et le corps de chaque message sont scellés avant d'atteindre la base de données, et
ouverts quand on les lit. Les identifiants, les instants, les séquences et les réglages de réception ne le sont pas : la
base de données sait qui a écrit à qui et quand, pas ce qu'ils ont dit.

## Le format

`KeyManagementMessageSealer` emploie le chiffrement par enveloppe du composant KeyManagement de Symfony : une clé de
données neuve par texte, enveloppée par une clé maîtresse du service de gestion de clés, le texte étant chiffré
localement avec AES-256-GCM. Un texte scellé se lit ainsi :

```text
pcv1.a256gcm.<master key>.<envelope>
```

| Partie | Ce que c'est |
|---|---|
| `pcv1` | La version du format du bundle |
| `a256gcm` | L'identifiant de son algorithme : l'enveloppe autonome du composant, AES-256-GCM |
| `<master key>` | Le nom de la clé maîtresse, en base64url sans remplissage |
| `<envelope>` | L'enveloppe du composant, son premier octet nommant son format, en base64url sans remplissage |

La version et l'algorithme sont authentifiés avec le texte, de sorte qu'aucun ne peut être remplacé par un autre ; le
nom de la clé maîtresse est comparé à celui que porte l'enveloppe. Chaque texte est lié à sa ligne : un sujet à
`phil_conversation.subject.<conversation>`, un corps à `phil_conversation.message.<message>`. Un texte scellé copié
dans une autre ligne ne s'y ouvre pas. Un texte qui ne s'ouvre pas lève `UnreadableMessage`.

Un format ultérieur prend une nouvelle version, et le sceau ouvre les textes de chaque version qu'il connaît.

## Les clés maîtresses

Un texte s'ouvre sous la clé maîtresse qu'il nomme, quelle que soit la clé courante, de sorte que les textes scellés
avant un changement de clé continuent de s'ouvrir tant que le service de gestion de clés détient leur clé. Chaque ligne
garde le nom de sa clé maîtresse à côté du texte scellé, dans `subject_key` et `body_key`, afin de trouver les textes
d'une clé précédente sans les ouvrir.

## Rotation de la clé maîtresse

1. Ajoutez la nouvelle clé au client, en gardant la précédente :

    ```yaml
    key_management:
        clients:
            local: 'sodium://?keys[conversations]=%env(OLD_KEY)%&keys[conversations-2027]=%env(NEW_KEY)%'
    ```

2. Faites de la nouvelle clé la clé courante ; les nouveaux textes sont dès lors scellés sous elle :

    ```yaml
    phil_conversation:
        encryption:
            key: conversations-2027
    ```

3. Scellez de nouveau les anciens textes, par lots :

    ```sh
    bin/console phil:conversation:rotate-keys --dry-run   # how many remain
    bin/console phil:conversation:rotate-keys --batch=500
    ```

4. Une fois qu'il n'en reste aucun, retirez la clé précédente du client.

Chaque texte est ouvert sous la clé qu'il nomme et réécrit seulement si aucune autre requête ne l'a changé entre-temps ;
un texte dont la clé a déjà quitté le service arrête la commande avec `UnreadableMessage`. `KeyRotation` fait la même
chose depuis le code : `pending()` compte, `rotate($batch)` scelle de nouveau un lot de chacun.

## Ce qu'il ne fait pas

Le bundle scelle au repos, pas de bout en bout : l'application lit les textes pour les montrer, et quiconque détient à
la fois la base de données et le service de gestion de clés les lit aussi. Le sceau protège une copie de la base de
données, une sauvegarde ou une réplique, prise sans les clés.
