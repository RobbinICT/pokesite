<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240504093246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pokemon (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, dex_nr INTEGER NOT NULL, name VARCHAR(255) NOT NULL, gen INTEGER NOT NULL, serie VARCHAR(255) NOT NULL, serie_nr INTEGER NOT NULL, list VARCHAR(255) NOT NULL, url VARCHAR(255))');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE pokemon');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
