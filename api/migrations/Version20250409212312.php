<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250409212312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE book DROP CONSTRAINT book_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book RENAME COLUMN id TO _id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER _id TYPE INT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ADD PRIMARY KEY (_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP CONSTRAINT FK_794381C616A2B381
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP CONSTRAINT review_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review RENAME COLUMN id TO _id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ALTER _id TYPE INT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C616A2B381 FOREIGN KEY (book_id) REFERENCES book (_id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD PRIMARY KEY (_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP CONSTRAINT fk_794381c616a2b381
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX review_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review RENAME COLUMN _id TO id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ALTER id TYPE INT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT fk_794381c616a2b381 FOREIGN KEY (book_id) REFERENCES book (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX book_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book RENAME COLUMN _id TO id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ALTER id TYPE INT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE book ADD PRIMARY KEY (id)
        SQL);
    }
}
