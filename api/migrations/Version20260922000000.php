<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial HookWatch MVP schema';
    }

    public function up(Schema $schema): void
    {
        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true]);
        $users->addColumn('email', 'string', ['length' => 180]);
        $users->addColumn('password_hash', 'string', ['length' => 255]);
        $users->addColumn('created_at', 'datetimetz_immutable', ['notnull' => true]);
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email']);

        $webhookEndpoints = $schema->createTable('webhook_endpoints');
        $webhookEndpoints->addColumn('id', 'integer', ['autoincrement' => true]);
        $webhookEndpoints->addColumn('user_id', 'integer', ['notnull' => true]);
        $webhookEndpoints->addColumn('name', 'string', ['length' => 120]);
        $webhookEndpoints->addColumn('public_token', 'string', ['length' => 255]);
        $webhookEndpoints->addColumn('forward_url', 'string', ['length' => 2048]);
        $webhookEndpoints->addColumn('active', 'boolean', ['default' => true]);
        $webhookEndpoints->addColumn('created_at', 'datetimetz_immutable', ['notnull' => true]);
        $webhookEndpoints->setPrimaryKey(['id']);
        $webhookEndpoints->addUniqueIndex(['public_token']);
        $webhookEndpoints->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        $webhookEvents = $schema->createTable('webhook_events');
        $webhookEvents->addColumn('id', 'integer', ['autoincrement' => true]);
        $webhookEvents->addColumn('endpoint_id', 'integer', ['notnull' => true]);
        $webhookEvents->addColumn('external_id', 'string', ['length' => 255, 'notnull' => false]);
        $webhookEvents->addColumn('event_type', 'string', ['length' => 120]);
        $webhookEvents->addColumn('payload', 'json', ['notnull' => true]);
        $webhookEvents->addColumn('headers', 'json', ['notnull' => true]);
        $webhookEvents->addColumn('received_at', 'datetimetz_immutable', ['notnull' => true]);
        $webhookEvents->addColumn('status', 'string', ['length' => 32]);
        $webhookEvents->setPrimaryKey(['id']);
        $webhookEvents->addUniqueIndex(['endpoint_id', 'external_id']);
        $webhookEvents->addForeignKeyConstraint('webhook_endpoints', ['endpoint_id'], ['id'], ['onDelete' => 'CASCADE']);

        $deliveryAttempts = $schema->createTable('delivery_attempts');
        $deliveryAttempts->addColumn('id', 'integer', ['autoincrement' => true]);
        $deliveryAttempts->addColumn('event_id', 'integer', ['notnull' => true]);
        $deliveryAttempts->addColumn('attempt_number', 'integer', ['notnull' => true]);
        $deliveryAttempts->addColumn('type', 'string', ['length' => 32]);
        $deliveryAttempts->addColumn('started_at', 'datetimetz_immutable', ['notnull' => true]);
        $deliveryAttempts->addColumn('finished_at', 'datetimetz_immutable', ['notnull' => false]);
        $deliveryAttempts->addColumn('http_status', 'integer', ['notnull' => false]);
        $deliveryAttempts->addColumn('response_body', 'text', ['notnull' => false]);
        $deliveryAttempts->addColumn('error', 'text', ['notnull' => false]);
        $deliveryAttempts->setPrimaryKey(['id']);
        $deliveryAttempts->addForeignKeyConstraint('webhook_events', ['event_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('delivery_attempts');
        $schema->dropTable('webhook_events');
        $schema->dropTable('webhook_endpoints');
        $schema->dropTable('users');
    }
}
