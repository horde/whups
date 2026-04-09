<?php

/**
 * Create whups base tables as of Whups 2.3.5
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */
class WhupsBaseTables extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $tableList = $this->tables();

        if (!in_array('whups_tickets', $tableList)) {
            $t = $this->createTable('whups_tickets', ['autoincrementKey' => false]);
            $t->column('ticket_id', 'integer', ['null' => false]);
            $t->column('ticket_summary', 'string', ['limit' => 255]);
            $t->column('user_id_requester', 'string', ['limit' => 255, 'null' => false]);
            $t->column('queue_id', 'integer', ['null' => false]);
            $t->column('version_id', 'integer');
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('state_id', 'integer', ['null' => false]);
            $t->column('priority_id', 'integer', ['null' => false]);
            $t->column('ticket_timestamp', 'integer', ['null' => false]);
            $t->column('ticket_due', 'integer');
            $t->column('date_updated', 'integer');
            $t->column('date_assigned', 'integer');
            $t->column('date_resolved', 'integer');
            $t->primaryKey(['ticket_id']);
            $t->end();

            $this->addIndex('whups_tickets', ['queue_id']);
            $this->addIndex('whups_tickets', ['state_id']);
            $this->addIndex('whups_tickets', ['user_id_requester']);
            $this->addIndex('whups_tickets', ['version_id']);
            $this->addIndex('whups_tickets', ['priority_id']);
        }

        if (!in_array('whups_ticket_owners', $tableList)) {
            $t = $this->createTable('whups_ticket_owners', ['autoincrementKey' => false]);
            $t->column('ticket_id', 'integer', ['null' => false]);
            $t->column('ticket_owner', 'string', ['null' => false, 'limit' => 255]);
            $t->primaryKey(['ticket_id', 'ticket_owner']);
            $t->end();

            $this->addIndex('whups_ticket_owners', 'ticket_id');
            $this->addIndex('whups_ticket_owners', 'ticket_owner');
        }

        if (!in_array('whups_guests', $tableList)) {
            $t = $this->createTable('whups_guests', ['autoincrementKey' => false]);
            $t->column('guest_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('guest_email', 'string', ['limit' => 255, 'null' => false]);
            $t->primaryKey(['guest_id']);
            $t->end();
        }

        if (!in_array('whups_queues', $tableList)) {
            $t = $this->createTable('whups_queues', ['autoincrementKey' => false]);
            $t->column('queue_id', 'integer', ['null' => false]);
            $t->column('queue_name', 'string', ['limit' => 64, 'null' => false]);
            $t->column('queue_description', 'string', ['limit' => 255]);
            $t->column('queue_versioned', 'smallint', ['default' => 0, 'null' => false]);
            $t->column('queue_slug', 'string', ['limit' => 64]);
            $t->column('queue_email', 'string', ['limit' => 64]);
            $t->primaryKey(['queue_id']);
            $t->end();
        }

        if (!in_array('whups_queues_users', $tableList)) {
            $t = $this->createTable('whups_queues_users', ['autoincrementKey' => false]);
            $t->column('queue_id', 'integer', ['null' => false]);
            $t->column('user_uid', 'string', ['limit' => 250, 'null' => false]);
            $t->primaryKey(['queue_id', 'user_uid']);
            $t->end();
        }

        if (!in_array('whups_types', $tableList)) {
            $t = $this->createTable('whups_types', ['autoincrementKey' => false]);
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('type_name', 'string', ['limit' => 64, 'null' => false]);
            $t->column('type_description', 'string', ['limit' => 255]);
            $t->primaryKey(['type_id']);
            $t->end();
        }

        if (!in_array('whups_types_queues', $tableList)) {
            $t = $this->createTable('whups_types_queues', ['autoincrementKey' => false]);
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('queue_id', 'integer', ['null' => false]);
            $t->column('type_default', 'smallint', ['null' => false, 'default' => 0]);
            $t->end();

            $this->addIndex('whups_types_queues', ['queue_id', 'type_id']);
        }

        if (!in_array('whups_states', $tableList)) {
            $t = $this->createTable('whups_states', ['autoincrementKey' => false]);
            $t->column('state_id', 'integer', ['null' => false]);
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('state_name', 'string', ['limit' => 64, 'null' => false]);
            $t->column('state_description', 'string', ['limit' => 255]);
            $t->column('state_category', 'string', ['limit' => 16]);
            $t->column('state_default', 'smallint', ['default' => 0, 'null' => false]);
            $t->primaryKey(['state_id']);
            $t->end();

            $this->addIndex('whups_states', ['type_id']);
            $this->addIndex('whups_states', ['state_category']);
        }

        if (!in_array('whups_replies', $tableList)) {
            $t = $this->createTable('whups_replies', ['autoincrementKey' => false]);
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('reply_id', 'integer', ['null' => false]);
            $t->column('reply_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('reply_text', 'text', ['null' => false]);
            $t->primaryKey(['reply_id']);
            $t->end();

            $this->addIndex('whups_replies', ['type_id']);
            $this->addIndex('whups_replies', ['reply_name']);
        }

        if (!in_array('whups_attributes_desc', $tableList)) {
            $t = $this->createTable('whups_attributes_desc', ['autoincrementKey' => false]);
            $t->column('attribute_id', 'integer', ['null' => false]);
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('attribute_name', 'string', ['null' => false, 'limit' => 64]);
            $t->column('attribute_description', 'string', ['null' => false, 'limit' => 255]);
            $t->column('attribute_type', 'string', ['default' => 'text', 'null' => false, 'limit' => 255]);
            $t->column('attribute_params', 'text');
            $t->column('attribute_required', 'smallint');
            $t->primaryKey(['attribute_id']);
            $t->end();
        }

        if (!in_array('whups_attributes', $tableList)) {
            $t = $this->createTable('whups_attributes', ['autoincrementKey' => false]);
            $t->column('ticket_id', 'integer', ['null' => false]);
            $t->column('attribute_id', 'integer', ['null' => false]);
            $t->column('attribute_value', 'string', ['limit' => 255]);
            $t->end();
        }

        if (!in_array('whups_comments', $tableList)) {
            $t = $this->createTable('whups_comments', ['autoincrementKey' => false]);
            $t->column('comment_id', 'integer', ['null' => false]);
            $t->column('ticket_id', 'integer', ['null' => false]);
            $t->column('user_id_creator', 'string', ['limit' => 255, 'null' => false]);
            $t->column('comment_text', 'text');
            $t->column('comment_timestamp', 'integer');
            $t->primaryKey(['comment_id']);
            $t->end();

            $this->addIndex('whups_comments', ['ticket_id']);
        }

        if (!in_array('whups_logs', $tableList)) {
            $t = $this->createTable('whups_logs', ['autoincrementKey' => false]);
            $t->column('log_id', 'integer', ['null' => false]);
            $t->column('transaction_id', 'integer', ['null' => false]);
            $t->column('ticket_id', 'integer', ['null' => false]);
            $t->column('log_timestamp', 'integer', ['null' => false]);
            $t->column('log_type', 'string', ['limit' => 255, 'null' => false]);
            $t->column('log_value', 'string', ['null' => false]);
            $t->column('log_value_num', 'integer');
            $t->column('user_id', 'string', ['limit' => 255, 'null' => false]);
            $t->primaryKey(['log_id']);
            $t->end();

            $this->addIndex('whups_logs', ['transaction_id']);
            $this->addIndex('whups_logs', ['ticket_id']);
            $this->addIndex('whups_logs', ['log_timestamp']);
        }

        if (!in_array('whups_priorities', $tableList)) {
            $t = $this->createTable('whups_priorities', ['autoincrementKey' => false]);
            $t->column('priority_id', 'integer', ['null' => false]);
            $t->column('type_id', 'integer', ['null' => false]);
            $t->column('priority_name', 'string', ['limit' => 64]);
            $t->column('priority_description', 'string', ['limit' => 255]);
            $t->column('priority_default', 'smallint', ['default' => 0, 'null' => false]);
            $t->primaryKey(['priority_id']);
            $t->end();

            $this->addIndex('whups_priorities', ['type_id']);
        }

        if (!in_array('whups_versions', $tableList)) {
            $t = $this->createTable('whups_versions', ['autoincrementKey' => false]);
            $t->column('version_id', 'integer', ['null' => false]);
            $t->column('queue_id', 'integer', ['null' => false]);
            $t->column('version_name', 'string', ['limit' => 64]);
            $t->column('version_description', 'string', ['limit' => 255]);
            $t->column('version_active', 'integer', ['default' => 1]);
            $t->primaryKey(['version_id']);
            $t->end();

            $this->addIndex('whups_versions', ['version_active']);
        }

        if (!in_array('whups_ticket_listeners', $tableList)) {
            $t = $this->createTable('whups_ticket_listeners', ['autoincrementKey' => false]);
            $t->column('ticket_id', 'integer', ['null' => false]);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->end();

            $this->addIndex('whups_ticket_listeners', ['ticket_id']);
        }

        if (!in_array('whups_queries', $tableList)) {
            $t = $this->createTable('whups_queries', ['autoincrementKey' => false]);
            $t->column('query_id', 'integer', ['null' => false]);
            $t->column('query_parameters', 'text');
            $t->column('query_object', 'text');
            $t->primaryKey(['query_id']);
            $t->end();
        }

        if (!in_array('whups_shares', $tableList)) {
            $t = $this->createTable('whups_shares', ['autoincrementKey' => false]);
            $t->column('share_id', 'integer', ['null' => false]);
            $t->column('share_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('share_owner', 'string', ['limit' => 255, 'null' => false]);
            $t->column('share_flags', 'smallint', ['default' => 0, 'null' => false]);
            $t->column('perm_creator', 'smallint', ['default' => 0, 'null' => false]);
            $t->column('perm_default', 'smallint', ['default' => 0, 'null' => false]);
            $t->column('perm_guest', 'smallint', ['default' => 0, 'null' => false]);
            $t->column('attribute_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('attribute_slug', 'string', ['limit' => 255]);
            $t->primaryKey(['share_id']);
            $t->end();

            $this->addIndex('whups_shares', ['share_name']);
            $this->addIndex('whups_shares', ['share_owner']);
            $this->addIndex('whups_shares', ['perm_creator']);
            $this->addIndex('whups_shares', ['perm_default']);
            $this->addIndex('whups_shares', ['perm_guest']);
        }

        if (!in_array('whups_shares_groups', $tableList)) {
            $t = $this->createTable('whups_shares_groups');
            $t->column('share_id', 'integer', ['null' => false]);
            $t->column('group_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('perm', 'smallint', ['null' => false]);
            $t->end();

            $this->addIndex('whups_shares_groups', ['share_id']);
            $this->addIndex('whups_shares_groups', ['group_uid']);
            $this->addIndex('whups_shares_groups', ['perm']);
        }

        if (!in_array('whups_shares_users', $tableList)) {
            $t = $this->createTable('whups_shares_users');

            $t->column('share_id', 'integer', ['null' => false]);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('perm', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('whups_shares_users', ['share_id']);
            $this->addIndex('whups_shares_users', ['user_uid']);
            $this->addIndex('whups_shares_users', ['perm']);
        }
    }

    /**
     * Downgrade to 0
     */
    public function down()
    {
        $this->dropTable('whups_tickets');
        $this->dropTable('whups_ticket_owners');
        $this->dropTable('whups_guests');
        $this->dropTable('whups_queues');
        $this->dropTable('whups_queues_users');
        $this->dropTable('whups_types');
        $this->dropTable('whups_types_queues');
        $this->dropTable('whups_states');
        $this->dropTable('whups_replies');
        $this->dropTable('whups_attributes_desc');
        $this->dropTable('whups_attributes');
        $this->dropTable('whups_comments');
        $this->dropTable('whups_logs');
        $this->dropTable('whups_priorities');
        $this->dropTable('whups_versions');
        $this->dropTable('whups_ticket_listeners');
        $this->dropTable('whups_queries');
        $this->dropTable('whups_shares');
        $this->dropTable('whups_shares_groups');
        $this->dropTable('whups_shares_users');
    }

}
