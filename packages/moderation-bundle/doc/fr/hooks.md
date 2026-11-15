<!-- phil-dev source-sha256=1f2b077c3b60848261cc087824d13b7cd227385d72ade6c148290e15d4c1f4db -->
# Crochets

## La soumission d'un contenu

Un paquet appelle `Submissions::submit($target, $author, $text)` quand un membre crée un contenu — une question, un
message de forum, une annonce, un commentaire —, avant de le publier :

```php
$submission = $submissions->submit(TargetReference::of('forum.post', $post->id), $author, $post->body);

match ($submission->verdict) {
    ScreeningVerdict::Block => throw new PostRefused(),
    ScreeningVerdict::Hold => $post->holdForModeration(),
    ScreeningVerdict::Allow, ScreeningVerdict::Flag => $post->publish($submission->text),
};
```

| Verdict | Ce qui s'est passé | Le paquet |
|---|---|---|
| `Allow` | Rien à noter | Publie |
| `Flag` | Un mot surveillé a demandé de signaler : un signalement attend, le contenu est publié | Publie |
| `Hold` | Un mot surveillé a demandé de retenir, ou le bac à sable du premier message l'a retenu : un signalement attend | Le garde non publié jusqu'à `Approve` |
| `Block` | Un mot surveillé l'a refusé ; rien n'atteint la file | Le refuse |

`text` est le texte à conserver, un mot remplacé ou censuré par les mots surveillés ; `null` quand aucun texte n'a été
donné.

## Le bac à sable du premier message

Pour les types de cibles de `sandbox.types`, le premier contenu d'un membre attend dans la file (source `sandbox`,
raison `first-post`). **Approuver un premier message retenu sort son auteur du bac à sable** ; son contenu suivant est
publié sur-le-champ. Le retirer le laisse dans le bac à sable. La politique est un port, `SandboxPolicy` : une
application qui retient selon une autre règle — un nombre de messages approuvés, l'ancienneté du compte — le remplace.

## Les mots surveillés

`WordScreen` lit un texte et répond par un `Screening` : un verdict, le texte à conserver et les mots qui ont
correspondu, que le signalement garde comme commentaire. La valeur par défaut, `NoWordScreen`, laisse tout passer ;
`phil/watched-words` apporte l'adaptateur qui bloque, censure, retient, signale, remplace, relie et étiquette.

## Le masquage temporaire par des membres de confiance

`TemporaryHides::hide($target, $comment)` permet à un membre d'un niveau de confiance élevé de masquer un contenu
sur-le-champ : le paquet de la cible le masque, et un signalement attend (source `trusted_hide`, raison
`hidden-by-member`), où un modérateur le rétablit ou le confirme. Qui est de confiance est un port, `TrustedHiders` ; la
valeur par défaut ne fait confiance à personne, et `phil/trust-levels` apporte l'adaptateur. `mayHide()` dit si le
membre courant le peut.

## Les profils

Quand l'application fournit un service `MemberProfiles`, les profils deviennent une cible de type `profile`, dont
l'identifiant est celui du membre : un profil est signalé, retenu et jugé comme n'importe quel contenu. `Hide` masque le
profil public ou réinitialise son nom affiché, son avatar et sa description, `Restore` le montre de nouveau, `Remove`
les vide — comme l'application les implémente.
