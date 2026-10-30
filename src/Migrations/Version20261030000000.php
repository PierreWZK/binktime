<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Schéma initial. SQLite en local, MySQL en production : un jeu de requêtes par plateforme. */
final class Version20261030000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisateurs, saisons, tournois, niveaux et inscriptions';
    }

    public function up(Schema $schema): void
    {
        $queries = $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? [
            'CREATE TABLE entry (id INT AUTO_INCREMENT NOT NULL, player_name VARCHAR(40) NOT NULL, rebuys INT NOT NULL, addons INT NOT NULL, finish_position INT DEFAULT NULL, eliminated_at DATETIME DEFAULT NULL, tournament_id INT NOT NULL, INDEX IDX_2B219D7033D1A3E7 (tournament_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
            'CREATE TABLE level (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, small_blind INT NOT NULL, big_blind INT NOT NULL, ante INT NOT NULL, minutes INT NOT NULL, is_break TINYINT NOT NULL, tournament_id INT NOT NULL, INDEX IDX_9AEACC1333D1A3E7 (tournament_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
            'CREATE TABLE organizer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(90) NOT NULL, city VARCHAR(80) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_99D47173989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
            'CREATE TABLE season (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL, organizer_id INT NOT NULL, INDEX IDX_F0E45BA9876C4DDA (organizer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
            'CREATE TABLE tournament (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, public_token VARCHAR(16) NOT NULL, admin_token VARCHAR(32) NOT NULL, buy_in INT NOT NULL, starting_stack INT NOT NULL, rebuy_price INT NOT NULL, rebuy_stack INT NOT NULL, addon_price INT NOT NULL, addon_stack INT NOT NULL, payouts JSON NOT NULL, status VARCHAR(10) NOT NULL, current_level INT NOT NULL, level_ends_at DATETIME DEFAULT NULL, remaining_seconds INT DEFAULT NULL, starts_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, organizer_id INT DEFAULT NULL, season_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BD5FB8D9AE981E3B (public_token), UNIQUE INDEX UNIQ_BD5FB8D9FF1D5AC1 (admin_token), INDEX IDX_BD5FB8D9876C4DDA (organizer_id), INDEX IDX_BD5FB8D94EC001D1 (season_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
            'ALTER TABLE entry ADD CONSTRAINT FK_2B219D7033D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE',
            'ALTER TABLE level ADD CONSTRAINT FK_9AEACC1333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE',
            'ALTER TABLE season ADD CONSTRAINT FK_F0E45BA9876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer (id) ON DELETE CASCADE',
            'ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D9876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer (id) ON DELETE CASCADE',
            'ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D94EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE SET NULL',
        ] : [
            'CREATE TABLE entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, player_name VARCHAR(40) NOT NULL, rebuys INTEGER NOT NULL, addons INTEGER NOT NULL, finish_position INTEGER DEFAULT NULL, eliminated_at DATETIME DEFAULT NULL, tournament_id INTEGER NOT NULL, CONSTRAINT FK_2B219D7033D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'CREATE INDEX IDX_2B219D7033D1A3E7 ON entry (tournament_id)',
            'CREATE TABLE level (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, position INTEGER NOT NULL, small_blind INTEGER NOT NULL, big_blind INTEGER NOT NULL, ante INTEGER NOT NULL, minutes INTEGER NOT NULL, is_break BOOLEAN NOT NULL, tournament_id INTEGER NOT NULL, CONSTRAINT FK_9AEACC1333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'CREATE INDEX IDX_9AEACC1333D1A3E7 ON level (tournament_id)',
            'CREATE TABLE organizer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(90) NOT NULL, city VARCHAR(80) DEFAULT NULL, created_at DATETIME NOT NULL)',
            'CREATE UNIQUE INDEX UNIQ_99D47173989D9B62 ON organizer (slug)',
            'CREATE TABLE season (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL, organizer_id INTEGER NOT NULL, CONSTRAINT FK_F0E45BA9876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'CREATE INDEX IDX_F0E45BA9876C4DDA ON season (organizer_id)',
            'CREATE TABLE tournament (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, public_token VARCHAR(16) NOT NULL, admin_token VARCHAR(32) NOT NULL, buy_in INTEGER NOT NULL, starting_stack INTEGER NOT NULL, rebuy_price INTEGER NOT NULL, rebuy_stack INTEGER NOT NULL, addon_price INTEGER NOT NULL, addon_stack INTEGER NOT NULL, payouts CLOB NOT NULL, status VARCHAR(10) NOT NULL, current_level INTEGER NOT NULL, level_ends_at DATETIME DEFAULT NULL, remaining_seconds INTEGER DEFAULT NULL, starts_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, organizer_id INTEGER DEFAULT NULL, season_id INTEGER DEFAULT NULL, CONSTRAINT FK_BD5FB8D9876C4DDA FOREIGN KEY (organizer_id) REFERENCES organizer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BD5FB8D94EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'CREATE UNIQUE INDEX UNIQ_BD5FB8D9AE981E3B ON tournament (public_token)',
            'CREATE UNIQUE INDEX UNIQ_BD5FB8D9FF1D5AC1 ON tournament (admin_token)',
            'CREATE INDEX IDX_BD5FB8D9876C4DDA ON tournament (organizer_id)',
            'CREATE INDEX IDX_BD5FB8D94EC001D1 ON tournament (season_id)',
        ];

        foreach ($queries as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['entry', 'level', 'tournament', 'season', 'organizer'] as $table) {
            $this->addSql('DROP TABLE '.$table);
        }
    }
}
