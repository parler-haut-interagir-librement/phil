<!-- phil-dev source-sha256=f9d59122f5fce10240d454950177668195e65e4e48d049534d301ea32556386f -->
# Exceptions

Chaque exception implémente `Phil\MentionsBundle\Domain\Exception\MentionsException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidMention` | `InvalidArgumentException` | Un identifiant, une position, un motif, un membre, une limite ou un identifiant de ressource est inutilisable |
| `MentionsRefused` | `RuntimeException` | Un texte dépasse `limits.text_length`, ou mentionne plus de membres que `limits.per_text` |

`MentionsRefused` provient de l'appel de l'application à `Mentions` ou à `MentionRecorder`, avant que quoi que ce soit
soit conservé : l'application l'affiche à la personne qui écrit et garde son texte.

`NotifyMentionedMember` laisse passer l'`InvalidNotification` du bundle de notifications pour un sujet ou un acteur qui
n'est pas l'un de ses identifiants.
