<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014193507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE file (id INT AUTO_INCREMENT NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, size INT NOT NULL, mime_type VARCHAR(255) NOT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_8C9F3610A2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE video_game ADD cover_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_game ADD CONSTRAINT FK_24BC6C50E5A0E336 FOREIGN KEY (cover_image_id) REFERENCES file (id)');
        $this->addSql('CREATE INDEX IDX_24BC6C50E5A0E336 ON video_game (cover_image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610A2B28FE8');
        $this->addSql('DROP TABLE file');
        $this->addSql('ALTER TABLE video_game DROP FOREIGN KEY FK_24BC6C50E5A0E336');
        $this->addSql('DROP INDEX IDX_24BC6C50E5A0E336 ON video_game');
        $this->addSql('ALTER TABLE video_game DROP cover_image_id');
    }
}
