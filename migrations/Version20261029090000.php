<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the LEGO wishlist flag to minifigures';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE minifigure ADD COLUMN wanted BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE minifigure DROP COLUMN wanted');
    }
}
