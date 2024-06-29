<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240629080023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE missing_pokemon (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, serie VARCHAR(255) NOT NULL, serie_nr VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE pokedex (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, dex_nr INTEGER NOT NULL, name VARCHAR(255) NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE missing_pokemon');
        $this->addSql('DROP TABLE pokedex');
    }
}
