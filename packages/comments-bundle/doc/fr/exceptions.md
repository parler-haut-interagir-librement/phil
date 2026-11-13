<!-- phil-dev source-sha256=430b554a2c4c75d6b47aac749f831ce2ca638d70139b4e27ad3f7c2e4324aade -->
# Exceptions

Chaque exception du bundle implémente `Phil\CommentsBundle\Domain\Exception\CommentsException`.

| Exception | Levée quand |
|---|---|
| `Domain\Exception\InvalidComment` | Une ancre, un identifiant de fil ou de commentaire, un commentaire, une suggestion ne peut pas être gardé ; une suggestion vise la page entière ; un auteur ou un acteur est nommé par une adresse de courriel ; un fil sur la page est déplacé ou rendu orphelin ; une décision vise un fil sans suggestion en attente ; une suggestion est appliquée à un passage qui n'est pas à sa place |
| `Application\Exception\NotFound` | Aucun fil ni commentaire n'a l'identifiant donné |
| `Application\Exception\CommentRefused` | La politique de l'application ne permet pas à l'auteur de faire ce qui est demandé |

`Phil\Resource\InvalidResource`, de `phil/resource`, est levée pour une référence qui n'est pas `kind:identifier`.

Les points de terminaison répondent à chacune avec son statut ; voyez [les points de terminaison](endpoints.md).
