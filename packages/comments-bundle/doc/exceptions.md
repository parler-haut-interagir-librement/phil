# Exceptions

Every exception of the bundle implements `Phil\CommentsBundle\Domain\Exception\CommentsException`.

| Exception | Thrown when |
|---|---|
| `Domain\Exception\InvalidComment` | An anchor, a thread or comment identifier, a comment, a suggestion that cannot be kept; a suggestion on the whole page; an author or an actor named by an email address; a thread on the page moved or orphaned; a decision on a thread without a pending suggestion; a suggestion applied to a passage that is not at its place |
| `Application\Exception\NotFound` | No thread or comment has the identifier given |
| `Application\Exception\CommentRefused` | The application's policy does not allow the author to do what was asked |

`Phil\Resource\InvalidResource`, from `phil/resource`, is thrown for a reference that is not `kind:identifier`.

The endpoints answer each with its status; see [endpoints](endpoints.md).
