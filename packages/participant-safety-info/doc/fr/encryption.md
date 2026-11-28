<!-- phil-dev source-sha256=d333d10b16a29e7eacd5107045df6847b58e13a7e6f705d6113d77e1a9b7a191 -->
# Chiffrement

## Comment un enregistrement est scellé

`SafetyInfoCodec` écrit l'information sous forme d'un objet JSON de version 1. `KeyManagement\KeyManagementSealer` le
scelle par le chiffrement d'enveloppe du composant KeyManagement de Symfony :

1. le KMS génère une clé de données neuve pour l'enregistrement et la retourne enveloppée par la clé maîtresse ;
2. le texte est chiffré localement avec AES-256-GCM sous cette clé de données, puis la clé de données est effacée ;
3. l'enveloppe — le nom de la clé maîtresse, la clé de données enveloppée, le nonce, l'étiquette et le texte chiffré —
   est stockée en base64 standard.

Le texte en clair n'atteint jamais le KMS et n'atteint jamais le magasin.

## Ce qui lie un enregistrement à sa ligne

Le contexte authentifié avec le texte se compose de `phil/participant-safety-info`, de la version `v1`, de
l'événement et du participant, séparés par le séparateur d'unité, qu'aucun identifiant ne peut contenir. Un
enregistrement copié vers un autre participant ou un autre événement ne s'ouvre pas : le coffre le refuse avec
`Exception\UnreadableSafetyInfo`. Un enregistrement altéré d'un seul octet ne s'ouvre pas non plus.

## Le décodage

Le texte ouvert est décodé strictement : 32 Kio au plus, une profondeur de 4, exactement les cinq clés de la version 1,
chaque valeur de son type, chaque champ revérifié par les constructeurs. Tout le reste est `UnreadableSafetyInfo`.

## La clé maîtresse

Le scelleur reçoit le nom de la clé maîtresse avec laquelle sceller. Chaque enveloppe nomme la clé maîtresse qui l'a
scellée : un enregistrement scellé avant le changement du nom configuré s'ouvre donc encore tant que le KMS détient
l'ancienne clé. Pour faire une rotation, configurez le nouveau nom : les nouveaux enregistrements l'utilisent, les plus
anciens s'ouvrent sous la leur jusqu'à leur purge avec leur événement.

## Ce qui reste en clair

Le magasin voit l'événement, le participant, le moment où l'enregistrement a été scellé et la date jusqu'à laquelle il
est gardé : ce dont il a besoin pour trouver et purger un enregistrement. `SafetyInfo` et `EmergencyContact`
n'exposent jamais leurs champs : `var_dump()`, `print_r()` et les débogueurs affichent `[sensitive]`, et leurs
constructeurs marquent chaque champ `#[SensitiveParameter]`, de sorte qu'une trace d'appels ne les montre jamais.
