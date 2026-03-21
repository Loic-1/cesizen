<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification tokens for user email confirmation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE email_verification_token (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', consumed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_email_verification_token_user_id (user_id), INDEX idx_email_verification_token_expires_at (expires_at), UNIQUE INDEX uniq_email_verification_token_hash (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE email_verification_token ADD CONSTRAINT FK_DA420FCAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_verification_token DROP FOREIGN KEY FK_DA420FCAA76ED395');
        $this->addSql('DROP TABLE email_verification_token');
    }
}
