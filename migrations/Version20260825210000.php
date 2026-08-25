<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create review table with company and rating indexes.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('review');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('company_name', 'string', ['length' => 255]);
        $table->addColumn('rating', 'integer');
        $table->addColumn('review_text', 'text');
        $table->addColumn('author_email', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['company_name'], 'idx_review_company_name');
        $table->addIndex(['rating'], 'idx_review_rating');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('review');
    }
}
