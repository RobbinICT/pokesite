<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026095400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop super actions, paradox rift and alphabetical order from config';
    }

    public function up(Schema $schema): void
    {
        // SQLite cannot drop columns in place: rebuild the table.
        $this->addSql('CREATE TEMPORARY TABLE __temp__config AS SELECT id, is_use_local_cards, is_use_local_minifigures FROM config');
        $this->addSql('DROP TABLE config');
        $this->addSql('CREATE TABLE config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, is_use_local_cards BOOLEAN NOT NULL, is_use_local_minifigures BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO config (id, is_use_local_cards, is_use_local_minifigures) SELECT id, is_use_local_cards, is_use_local_minifigures FROM __temp__config');
        $this->addSql('DROP TABLE __temp__config');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__config AS SELECT id, is_use_local_cards, is_use_local_minifigures FROM config');
        $this->addSql('DROP TABLE config');
        $this->addSql('CREATE TABLE config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, is_use_local_cards BOOLEAN NOT NULL, is_use_local_minifigures BOOLEAN DEFAULT 1 NOT NULL, is_super_actions_enabled BOOLEAN DEFAULT 0 NOT NULL, is_paradox_rift_exclude BOOLEAN DEFAULT 0 NOT NULL, is_in_alphabetical_order BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('INSERT INTO config (id, is_use_local_cards, is_use_local_minifigures) SELECT id, is_use_local_cards, is_use_local_minifigures FROM __temp__config');
        $this->addSql('DROP TABLE __temp__config');
    }
}
