<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * The tables of the bundle, added to the schema Doctrine generates so that the application's migrations create them:
 *
 * - `phil_privacy_account_deletion`: one row per request, found by person and by what is due;
 * - `phil_privacy_consent_proof`: one row per choice, only ever added, naming no one;
 * - `phil_privacy_guest_request`: one row per request made without an account, while its link is valid, its address
 *   sealed;
 * - `phil_privacy_incident`: the register of confidentiality incidents;
 * - `phil_privacy_email_tracking`: each member's last choice about tracked emails, one row per member.
 *
 * Every instant is a `datetime_utc_immutable` column, written and read through {@see UtcMoments}.
 */
final class PrivacyTables
{
    public const string DELETIONS = 'phil_privacy_account_deletion';

    public const string PROOFS = 'phil_privacy_consent_proof';

    public const string GUEST_REQUESTS = 'phil_privacy_guest_request';

    public const string INCIDENTS = 'phil_privacy_incident';

    public const string EMAIL_TRACKING = 'phil_privacy_email_tracking';

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::DELETIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::DELETIONS)
                ->addColumn(self::string('id', 32))
                ->addColumn(self::string('subject', DataSubject::MAX_LENGTH))
                ->addColumn(self::string('place', 16))
                ->addColumn(self::moment('requested_at'))
                ->addColumn(self::moment('effective_at'))
                ->addColumn(self::maybeMoment('closed_at'))
                ->addColumn(Column::editor()->setUnquotedName('export_reference')->setTypeName(Types::STRING)->setLength(32)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_privacy_deletion_subject')->setUnquotedColumnNames('subject', 'place')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_privacy_deletion_due')->setUnquotedColumnNames('place', 'effective_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::PROOFS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::PROOFS)
                ->addColumn(self::string('id', 32))
                ->addColumn(self::string('version', 32))
                ->addColumn(Column::editor()->setUnquotedName('granted')->setTypeName(Types::TEXT)->setNotNull(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('global_privacy_control')->setTypeName(Types::BOOLEAN)->setNotNull(true)->create())
                ->addColumn(self::moment('decided_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_privacy_proof_decided')->setUnquotedColumnNames('decided_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::GUEST_REQUESTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::GUEST_REQUESTS)
                ->addColumn(self::string('id', 32))
                ->addColumn(self::string('kind', 16))
                ->addColumn(self::text('email'))
                ->addColumn(self::string('token_hash', 64))
                ->addColumn(self::moment('requested_at'))
                ->addColumn(self::moment('expires_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_privacy_guest_expires')->setUnquotedColumnNames('expires_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::INCIDENTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::INCIDENTS)
                ->addColumn(self::string('id', 32))
                ->addColumn(self::text('summary'))
                ->addColumn(self::moment('discovered_at'))
                ->addColumn(self::maybeMoment('occurred_at'))
                ->addColumn(self::text('categories'))
                ->addColumn(Column::editor()->setUnquotedName('affected')->setTypeName(Types::INTEGER)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('serious_risk')->setTypeName(Types::BOOLEAN)->setNotNull(true)->create())
                ->addColumn(self::text('measures'))
                ->addColumn(self::maybeMoment('authority_notified_at'))
                ->addColumn(self::maybeMoment('subjects_notified_at'))
                ->addColumn(self::maybeMoment('closed_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_privacy_incident_discovered')->setUnquotedColumnNames('discovered_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::EMAIL_TRACKING)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::EMAIL_TRACKING)
                ->addColumn(self::string('subject', DataSubject::MAX_LENGTH))
                ->addColumn(self::string('version', 32))
                ->addColumn(Column::editor()->setUnquotedName('opens')->setTypeName(Types::BOOLEAN)->setNotNull(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('clicks')->setTypeName(Types::BOOLEAN)->setNotNull(true)->create())
                ->addColumn(self::moment('decided_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subject')->create())
                ->create());
        }

        return $editor->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function text(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->setNotNull(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function maybeMoment(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(false)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function moment(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(true)->create();
    }
}
