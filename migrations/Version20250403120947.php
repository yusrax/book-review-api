<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250403120947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review_like (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, review_id INT NOT NULL, INDEX IDX_4ED70DABA76ED395 (user_id), INDEX IDX_4ED70DAB3E2E969B (review_id), UNIQUE INDEX unique_review_like (user_id, review_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review_like ADD CONSTRAINT FK_4ED70DABA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_like ADD CONSTRAINT FK_4ED70DAB3E2E969B FOREIGN KEY (review_id) REFERENCES review (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review_like DROP FOREIGN KEY FK_4ED70DABA76ED395');
        $this->addSql('ALTER TABLE review_like DROP FOREIGN KEY FK_4ED70DAB3E2E969B');
        $this->addSql('DROP TABLE review_like');
    }
}
