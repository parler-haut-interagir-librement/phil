<!-- phil-dev source-sha256=0b19843263046eb6098371bb11574088c53a96713248eb5ecf2aac287f228141 -->
# Règles d'accès

Tout chemin vers l'information passe par `SafetyInfoVault`. Chaque méthode reçoit l'identifiant d'une personne que
l'application a déjà authentifiée : le coffre décide ce que cette personne peut faire, jamais qui elle est. Un
identifiant compte de 1 à 255 octets sans caractère de contrôle ; utilisez un identifiant stable, jamais une adresse
courriel.

## Les chemins

| Chemin | Qui | Quand | Journalisé | Événement |
|---|---|---|---|---|
| `submit()` | Le participant | Jusqu'à la fermeture de la fenêtre de l'événement | Non | `SafetyInfoSubmitted` |
| `readOwn()` | Le participant | En tout temps, tant que l'enregistrement existe | Non | Aucun |
| `copy()` | Le participant, à sa demande | Jusqu'à la fermeture de la fenêtre de l'événement vers lequel il copie | Non | `SafetyInfoSubmitted` |
| `erase()` | Le participant | En tout temps | Non | `SafetyInfoErased` |
| `readingsOf()` | Le participant | En tout temps | Non | Aucun |
| `read()` | `Role::Responsible` de l'événement | Tant que sa fenêtre est ouverte | Oui, avant l'ouverture | `SafetyInfoRead` |
| `breakGlass()` | `Role::Staff` ou `Role::Responsible` | Tant que sa fenêtre est ouverte, avec une raison de 10 à 500 caractères | Oui, avec la raison, avant l'ouverture | `SafetyInfoRead` avec `AccessReason::BreakGlass` |
| `purge()` | Une tâche planifiée | En tout temps | Non | Aucun |

L'application n'appelle `readOwn()`, `copy()`, `erase()` et `readingsOf()` qu'avec l'identifiant de la personne
connectée : ces chemins lui font confiance pour être le participant.

## La fenêtre d'accès

`Contract\AccessWindows` donne à chaque événement une `AccessWindow` : de `opensAt`, inclus, à `closesAt`, exclu. Un
événement sans fenêtre n'accepte ni soumission ni lecture. Un enregistrement est gardé jusqu'à la fermeture de la
fenêtre, puis `purge()` le supprime ; un participant peut l'effacer avant.

## Ce que dit un refus

`read()` et `breakGlass()` refusent avec `Exception\AccessDenied`, dont le message est le même quelle que soit la
raison : une personne sans le rôle, une fenêtre fermée, un événement sans fenêtre. Une lecture refusée n'écrit rien au
journal et ne déclenche aucun événement.

Un responsable qui peut lire apprend qu'un participant n'a rien envoyé : la méthode retourne `null`, et rien n'est
journalisé puisque rien n'a été lu.

## Le journal

Le coffre écrit un `AccessRecord` dans `Contract\AccessJournal` avant d'ouvrir l'information. Un journal qui lance une
exception arrête la lecture : aucun texte en clair ne quitte le coffre sans sa trace. Le journal est gardé à part de
l'information et survit à sa purge, pendant la durée que lui donne le calendrier de conservation de l'application. Le
participant le lit par `readingsOf()`, du plus récent au plus ancien, de 1 à 500 enregistrements à la fois.

## Le bris de glace

Un membre de l'équipe qui fait face à une urgence lit l'information avec `breakGlass()` et une raison. La raison va au
journal, jamais à l'événement ; l'événement `SafetyInfoRead` porte `AccessReason::BreakGlass`, et un écouteur avise les
responsables de l'événement, qui lisent la raison dans le journal.

## Les événements

Chaque événement ne porte que des identifiants — l'événement, le participant, le lecteur, la raison de l'accès —,
jamais un champ de l'information. Un écouteur lit ce dont il a besoin auprès de l'application.
