<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert legacy CHAR(36) UUID columns to BINARY(16) for MariaDB/MySQL.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610D218E35');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66A76ED395');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_65DA6B8AA76ED395');

        $this->addSql("ALTER TABLE `user` ADD id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE article ADD id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD user_id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE file ADD id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD article_id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE refresh_token ADD id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD user_id_bin BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");

        $this->addSql("UPDATE `user` SET id_bin = UNHEX(REPLACE(id, '-', ''))");
        $this->addSql("UPDATE article SET id_bin = UNHEX(REPLACE(id, '-', '')), user_id_bin = UNHEX(REPLACE(user_id, '-', ''))");
        $this->addSql("UPDATE file SET id_bin = UNHEX(REPLACE(id, '-', '')), article_id_bin = UNHEX(REPLACE(article_id, '-', ''))");
        $this->addSql("UPDATE refresh_token SET id_bin = UNHEX(REPLACE(id, '-', '')), user_id_bin = UNHEX(REPLACE(user_id, '-', ''))");

        $this->addSql('ALTER TABLE file DROP INDEX idx_file_article_id');
        $this->addSql('ALTER TABLE article DROP INDEX idx_article_user_id');
        $this->addSql('ALTER TABLE refresh_token DROP INDEX idx_refresh_token_user_id');

        $this->addSql("ALTER TABLE `user` DROP PRIMARY KEY, DROP COLUMN id");
        $this->addSql('ALTER TABLE article DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN user_id');
        $this->addSql('ALTER TABLE file DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN article_id');
        $this->addSql('ALTER TABLE refresh_token DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN user_id');

        $this->addSql("ALTER TABLE `user` CHANGE id_bin id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id)");
        $this->addSql("ALTER TABLE article CHANGE id_bin id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE user_id_bin user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id), ADD INDEX idx_article_user_id (user_id)");
        $this->addSql("ALTER TABLE file CHANGE id_bin id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE article_id_bin article_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id), ADD INDEX idx_file_article_id (article_id)");
        $this->addSql("ALTER TABLE refresh_token CHANGE id_bin id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE user_id_bin user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id), ADD INDEX idx_refresh_token_user_id (user_id)");

        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610D218E35 FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_65DA6B8AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $userIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(id), 1, 8), '-', SUBSTR(HEX(id), 9, 4), '-', SUBSTR(HEX(id), 13, 4), '-', SUBSTR(HEX(id), 17, 4), '-', SUBSTR(HEX(id), 21, 12)))";
        $articleIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(id), 1, 8), '-', SUBSTR(HEX(id), 9, 4), '-', SUBSTR(HEX(id), 13, 4), '-', SUBSTR(HEX(id), 17, 4), '-', SUBSTR(HEX(id), 21, 12)))";
        $articleUserIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(user_id), 1, 8), '-', SUBSTR(HEX(user_id), 9, 4), '-', SUBSTR(HEX(user_id), 13, 4), '-', SUBSTR(HEX(user_id), 17, 4), '-', SUBSTR(HEX(user_id), 21, 12)))";
        $fileIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(id), 1, 8), '-', SUBSTR(HEX(id), 9, 4), '-', SUBSTR(HEX(id), 13, 4), '-', SUBSTR(HEX(id), 17, 4), '-', SUBSTR(HEX(id), 21, 12)))";
        $fileArticleIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(article_id), 1, 8), '-', SUBSTR(HEX(article_id), 9, 4), '-', SUBSTR(HEX(article_id), 13, 4), '-', SUBSTR(HEX(article_id), 17, 4), '-', SUBSTR(HEX(article_id), 21, 12)))";
        $refreshTokenIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(id), 1, 8), '-', SUBSTR(HEX(id), 9, 4), '-', SUBSTR(HEX(id), 13, 4), '-', SUBSTR(HEX(id), 17, 4), '-', SUBSTR(HEX(id), 21, 12)))";
        $refreshTokenUserIdCharExpr = "LOWER(CONCAT(SUBSTR(HEX(user_id), 1, 8), '-', SUBSTR(HEX(user_id), 9, 4), '-', SUBSTR(HEX(user_id), 13, 4), '-', SUBSTR(HEX(user_id), 17, 4), '-', SUBSTR(HEX(user_id), 21, 12)))";

        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F3610D218E35');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66A76ED395');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_65DA6B8AA76ED395');

        $this->addSql("ALTER TABLE `user` ADD id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE article ADD id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD user_id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE file ADD id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD article_id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE refresh_token ADD id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)', ADD user_id_char CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");

        $this->addSql(sprintf("UPDATE `user` SET id_char = %s", $userIdCharExpr));
        $this->addSql(sprintf('UPDATE article SET id_char = %s, user_id_char = %s', $articleIdCharExpr, $articleUserIdCharExpr));
        $this->addSql(sprintf('UPDATE file SET id_char = %s, article_id_char = %s', $fileIdCharExpr, $fileArticleIdCharExpr));
        $this->addSql(sprintf('UPDATE refresh_token SET id_char = %s, user_id_char = %s', $refreshTokenIdCharExpr, $refreshTokenUserIdCharExpr));

        $this->addSql('ALTER TABLE file DROP INDEX idx_file_article_id');
        $this->addSql('ALTER TABLE article DROP INDEX idx_article_user_id');
        $this->addSql('ALTER TABLE refresh_token DROP INDEX idx_refresh_token_user_id');

        $this->addSql("ALTER TABLE `user` DROP PRIMARY KEY, DROP COLUMN id");
        $this->addSql('ALTER TABLE article DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN user_id');
        $this->addSql('ALTER TABLE file DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN article_id');
        $this->addSql('ALTER TABLE refresh_token DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN user_id');

        $this->addSql("ALTER TABLE `user` CHANGE id_char id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id)");
        $this->addSql("ALTER TABLE article CHANGE id_char id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE user_id_char user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id), ADD INDEX idx_article_user_id (user_id)");
        $this->addSql("ALTER TABLE file CHANGE id_char id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE article_id_char article_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id), ADD INDEX idx_file_article_id (article_id)");
        $this->addSql("ALTER TABLE refresh_token CHANGE id_char id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE user_id_char user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', ADD PRIMARY KEY (id), ADD INDEX idx_refresh_token_user_id (user_id)");

        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F3610D218E35 FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_65DA6B8AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }
}
