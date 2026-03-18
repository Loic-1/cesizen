<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user, article, file, and refresh_token tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE `user` (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, is_verified TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_user_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE article (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', title VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_article_created_at (created_at), INDEX idx_article_user_id (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE file (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', article_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', original_name VARCHAR(255) NOT NULL, storage_path VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_file_article_id (article_id), INDEX idx_file_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE refresh_token (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_refresh_token_hash (token_hash), INDEX idx_refresh_token_user_id (user_id), INDEX idx_refresh_token_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610D218E35 FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_65DA6B8AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610D218E35');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66A76ED395');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_65DA6B8AA76ED395');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE `user`');
    }
}
