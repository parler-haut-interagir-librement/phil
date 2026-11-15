<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;

/**
 * The seven tables of the bundle under one prefix: the nonces used once, the login states of the tool, the access
 * tokens of the platform, the keys, the line items and the scores of the platform, and the scores the tool still has
 * to send.
 *
 * Identifiers compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, `C` on PostgreSQL; SQLite compares bytes
 * already. Moments are integers of seconds since the epoch, in UTC.
 */
final readonly class LtiTables
{
    public const string DEFAULT_PREFIX = 'phil_lti_';

    public const string PREFIX_PATTERN = '/^[a-z_][a-z0-9_]{0,39}$/D';

    /** @var non-empty-string */
    public string $nonces;

    /** @var non-empty-string */
    public string $loginStates;

    /** @var non-empty-string */
    public string $accessTokens;

    /** @var non-empty-string */
    public string $keys;

    /** @var non-empty-string */
    public string $lineItems;

    /** @var non-empty-string */
    public string $scores;

    /** @var non-empty-string */
    public string $pendingScores;

    public function __construct(
        public string $prefix = self::DEFAULT_PREFIX,
    ) {
        if (1 !== preg_match(self::PREFIX_PATTERN, $prefix)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a prefix of table names: 1 to 40 lowercase letters, digits or "_", not starting with a digit.', mb_strimwidth($prefix, 0, 60, '…')));
        }

        $this->nonces = $prefix.'nonce';
        $this->loginStates = $prefix.'login_state';
        $this->accessTokens = $prefix.'access_token';
        $this->keys = $prefix.'key';
        $this->lineItems = $prefix.'line_item';
        $this->scores = $prefix.'score';
        $this->pendingScores = $prefix.'pending_score';
    }

    /**
     * @return list<non-empty-string>
     */
    public function names(): array
    {
        return [$this->nonces, $this->loginStates, $this->accessTokens, $this->keys, $this->lineItems, $this->scores, $this->pendingScores];
    }

    /**
     * Adds the tables a schema lacks: the one the ORM generates, through `LtiSchemaListener`, or one that
     * `phil:lti:schema` builds.
     */
    public function configureSchema(Schema $schema, AbstractPlatform $platform): Schema
    {
        $editor = $schema->edit();

        foreach ($this->definitions($platform) as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return list<Table>
     */
    public function definitions(AbstractPlatform $platform): array
    {
        return [
            Table::editor()->setUnquotedName($this->nonces)
                ->setColumns($this->id($platform, 'scope', 64), $this->id($platform, 'value_hash', 64), $this->moment('expires_at'))
                ->setPrimaryKeyConstraint($this->primaryKey('scope', 'value_hash'))
                ->setIndexes($this->index($this->nonces.'_expiry', 'expires_at'))
                ->create(),
            Table::editor()->setUnquotedName($this->loginStates)
                ->setColumns($this->id($platform, 'state_hash', 64), $this->text('nonce', 255), $this->text('issuer', 1024), $this->text('client_id', 255), $this->text('target', 2048), $this->moment('expires_at'))
                ->setPrimaryKeyConstraint($this->primaryKey('state_hash'))
                ->setIndexes($this->index($this->loginStates.'_expiry', 'expires_at'))
                ->create(),
            Table::editor()->setUnquotedName($this->accessTokens)
                ->setColumns($this->id($platform, 'token_hash', 64), $this->text('client_id', 255), $this->text('scopes', 1024), $this->moment('expires_at'))
                ->setPrimaryKeyConstraint($this->primaryKey('token_hash'))
                ->setIndexes($this->index($this->accessTokens.'_expiry', 'expires_at'))
                ->create(),
            Table::editor()->setUnquotedName($this->keys)
                ->setColumns($this->id($platform, 'kid', 64), Column::editor()->setUnquotedName('sealed')->setTypeName(Types::TEXT)->create(), $this->moment('created_at'), $this->moment('retired_at', true))
                ->setPrimaryKeyConstraint($this->primaryKey('kid'))
                ->create(),
            Table::editor()->setUnquotedName($this->lineItems)
                ->setColumns(
                    $this->id($platform, 'id', 64),
                    $this->id($platform, 'client_id', 255),
                    $this->id($platform, 'context_id', 255),
                    Column::editor()->setUnquotedName('score_maximum')->setTypeName(Types::FLOAT)->create(),
                    $this->text('label', 4096),
                    $this->text('resource_id', 255, true),
                    $this->text('resource_link_id', 255, true),
                    $this->text('tag', 255, true),
                    $this->text('start_at', 64, true),
                    $this->text('end_at', 64, true),
                    Column::editor()->setUnquotedName('grades_released')->setTypeName(Types::SMALLINT)->setNotNull(false)->create(),
                )
                ->setPrimaryKeyConstraint($this->primaryKey('id'))
                ->setIndexes($this->index($this->lineItems.'_context', 'client_id', 'context_id'))
                ->create(),
            Table::editor()->setUnquotedName($this->scores)
                ->setColumns($this->id($platform, 'line_item_id', 64), $this->id($platform, 'user_id', 255), Column::editor()->setUnquotedName('payload')->setTypeName(Types::TEXT)->create(), Column::editor()->setUnquotedName('scored_at')->setTypeName(Types::BIGINT)->create())
                ->setPrimaryKeyConstraint($this->primaryKey('line_item_id', 'user_id'))
                ->create(),
            Table::editor()->setUnquotedName($this->pendingScores)
                ->setColumns($this->id($platform, 'id', 64), $this->text('issuer', 1024), $this->text('client_id', 255), $this->text('line_item', 2048), Column::editor()->setUnquotedName('payload')->setTypeName(Types::TEXT)->create(), $this->moment('created_at'))
                ->setPrimaryKeyConstraint($this->primaryKey('id'))
                ->create(),
        ];
    }

    /**
     * @param non-empty-string $name
     */
    private function id(AbstractPlatform $platform, string $name, int $length): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $column->setCollation('C');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private function text(string $name, int $length, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private function moment(string $name, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BIGINT)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string ...$columns
     */
    private function primaryKey(string ...$columns): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$columns)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string ...$columns
     */
    private function index(string $name, string ...$columns): Index
    {
        return Index::editor()->setUnquotedName($name)->setUnquotedColumnNames(...$columns)->create();
    }
}
