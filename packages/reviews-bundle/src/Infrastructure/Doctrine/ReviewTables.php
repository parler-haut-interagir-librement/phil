<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The tables of the reviews: `phil_review`, one row per review, unique by subject and author; `phil_review_score` and
 * `phil_review_superlative`, the scores of its criteria and the labels it gave; `phil_review_vote`, the votes of
 * helpfulness, unique by review and voter; `phil_review_subject`, the ratings of each subject computed from its
 * published reviews and whether its reviews are suspended. Moments are kept in UTC.
 */
final class ReviewTables
{
    public const string REVIEW = 'phil_review';

    public const string SCORE = 'phil_review_score';

    public const string SUPERLATIVE = 'phil_review_superlative';

    public const string VOTE = 'phil_review_vote';

    public const string SUBJECT = 'phil_review_subject';

    private const int CODE_LENGTH = 64;

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, each left as it is when the schema already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::REVIEW)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::REVIEW)
                ->addColumn(self::uuid('id'))
                ->addColumn(self::column('subject_type', Types::STRING, true, self::CODE_LENGTH))
                ->addColumn(self::column('subject_id', Types::STRING, true, SubjectRef::MAX_ID_LENGTH))
                ->addColumn(self::column('author', Types::STRING, false, MemberId::MAX_LENGTH))
                ->addColumn(self::column('overall', Types::SMALLINT, true))
                ->addColumn(self::column('title', Types::STRING, false, 150))
                ->addColumn(self::column('body', Types::TEXT, false))
                ->addColumn(self::column('compensated', Types::BOOLEAN, true))
                ->addColumn(self::column('compensation_note', Types::STRING, false, 200))
                ->addColumn(self::column('origin', Types::STRING, true, 16))
                ->addColumn(self::column('verification', Types::STRING, false, 16))
                ->addColumn(self::column('status', Types::STRING, true, 16))
                ->addColumn(self::column('removal_criterion', Types::STRING, false, self::CODE_LENGTH))
                ->addColumn(self::column('written_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('edited_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('withdrawn_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('response', Types::TEXT, false))
                ->addColumn(self::column('response_by', Types::STRING, false, MemberId::MAX_LENGTH))
                ->addColumn(self::column('response_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('contest_evidence', Types::TEXT, false))
                ->addColumn(self::column('contest_filed_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('contest_outcome', Types::STRING, false, 16))
                ->addColumn(self::column('contest_settled_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('helpful_count', Types::INTEGER, true))
                ->addColumn(self::column('unhelpful_count', Types::INTEGER, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName('uniq_phil_review_author')->setUnquotedColumnNames('subject_type', 'subject_id', 'author')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_review_subject')->setUnquotedColumnNames('subject_type', 'subject_id', 'status', 'written_at')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_review_author')->setUnquotedColumnNames('author')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_review_responder')->setUnquotedColumnNames('response_by')->create())
                ->create());
        }

        if (!$schema->hasTable(self::SCORE)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SCORE)
                ->addColumn(self::uuid('review_id'))
                ->addColumn(self::column('criterion', Types::STRING, true, self::CODE_LENGTH))
                ->addColumn(self::column('score', Types::SMALLINT, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id', 'criterion')->create())
                ->create());
        }

        if (!$schema->hasTable(self::SUPERLATIVE)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SUPERLATIVE)
                ->addColumn(self::uuid('review_id'))
                ->addColumn(self::column('label', Types::STRING, true, self::CODE_LENGTH))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id', 'label')->create())
                ->create());
        }

        if (!$schema->hasTable(self::VOTE)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::VOTE)
                ->addColumn(self::uuid('review_id'))
                ->addColumn(self::column('voter', Types::STRING, true, MemberId::MAX_LENGTH))
                ->addColumn(self::column('helpful', Types::SMALLINT, true))
                ->addColumn(self::column('voted_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id', 'voter')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_review_vote_voter')->setUnquotedColumnNames('voter')->create())
                ->create());
        }

        if (!$schema->hasTable(self::SUBJECT)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SUBJECT)
                ->addColumn(self::column('subject_type', Types::STRING, true, self::CODE_LENGTH))
                ->addColumn(self::column('subject_id', Types::STRING, true, SubjectRef::MAX_ID_LENGTH))
                ->addColumn(self::column('review_count', Types::INTEGER, true))
                ->addColumn(self::column('rating_sum', Types::INTEGER, true))
                ->addColumn(self::column('foreign_count', Types::INTEGER, true))
                ->addColumn(self::column('weighted', Types::FLOAT, false))
                ->addColumn(self::column('criteria', Types::JSON, true))
                ->addColumn(self::column('superlatives', Types::JSON, true))
                ->addColumn(self::column('suspended', Types::BOOLEAN, true))
                ->addColumn(self::column('version', Types::BIGINT, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subject_type', 'subject_id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_review_subject_rank')->setUnquotedColumnNames('subject_type', 'weighted')->create())
                ->create());
        }

        return $editor->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function uuid(string $name): Column
    {
        return self::column($name, Types::STRING, true, 36)->edit()->setFixed(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $required, ?int $length = null): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required)->create();
    }
}
