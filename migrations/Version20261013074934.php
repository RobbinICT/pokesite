<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013074934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE minifigure (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, identifier VARCHAR(255) NOT NULL, series VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7289792F772E836A ON minifigure (identifier)');
        // DEFAULT required: SQLite rejects adding a NOT NULL column to a table that already has rows.
        $this->addSql('ALTER TABLE config ADD COLUMN is_use_local_minifigures BOOLEAN NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE minifigure');
        $this->addSql('CREATE TEMPORARY TABLE __temp__config AS SELECT id, is_super_actions_enabled, is_paradox_rift_exclude, is_use_local_cards, is_in_alphabetical_order FROM config');
        $this->addSql('DROP TABLE config');
        $this->addSql('CREATE TABLE config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, is_super_actions_enabled BOOLEAN NOT NULL, is_paradox_rift_exclude BOOLEAN NOT NULL, is_use_local_cards BOOLEAN NOT NULL, is_in_alphabetical_order BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO config (id, is_super_actions_enabled, is_paradox_rift_exclude, is_use_local_cards, is_in_alphabetical_order) SELECT id, is_super_actions_enabled, is_paradox_rift_exclude, is_use_local_cards, is_in_alphabetical_order FROM __temp__config');
        $this->addSql('DROP TABLE __temp__config');
    }
}
