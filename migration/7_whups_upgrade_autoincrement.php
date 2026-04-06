<?php

/**
 * Change columns to autoincrement.
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */
class WhupsUpgradeAutoIncrement extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $this->changeColumn('whups_tickets', 'ticket_id', 'autoincrementKey');
        if (in_array('whups_tickets_seq', $this->tables())) {
            $this->dropTable('whups_tickets_seq');
        }

        $this->changeColumn('whups_queues', 'queue_id', 'autoincrementKey');
        if (in_array('whups_queues_seq', $this->tables())) {
            $this->dropTable('whups_queues_seq');
        }

        $this->changeColumn('whups_types', 'type_id', 'autoincrementKey');
        if (in_array('whups_types_seq', $this->tables())) {
            $this->dropTable('whups_types_seq');
        }

        $this->changeColumn('whups_states', 'state_id', 'autoincrementKey');
        if (in_array('whups_states_seq', $this->tables())) {
            $this->dropTable('whups_states_seq');
        }

        $this->changeColumn('whups_replies', 'reply_id', 'autoincrementKey');
        if (in_array('whups_replies_seq', $this->tables())) {
            $this->dropTable('whups_replies_seq');
        }

        $this->changeColumn('whups_attributes_desc', 'attribute_id', 'autoincrementKey');
        if (in_array('whups_attributes_desc_seq', $this->tables())) {
            $this->dropTable('whups_attributes_desc_seq');
        }

        $this->changeColumn('whups_comments', 'comment_id', 'autoincrementKey');
        if (in_array('whups_comments_seq', $this->tables())) {
            $this->dropTable('whups_comments_seq');
        }

        $this->changeColumn('whups_logs', 'log_id', 'autoincrementKey');
        if (in_array('whups_logs_seq', $this->tables())) {
            $this->dropTable('whups_logs_seq');
        }

        $this->changeColumn('whups_priorities', 'priority_id', 'autoincrementKey');
        if (in_array('whups_priorities_seq', $this->tables())) {
            $this->dropTable('whups_priorities_seq');
        }

        $this->changeColumn('whups_versions', 'version_id', 'autoincrementKey');
        if (in_array('whups_version_seq', $this->tables())) {
            $this->dropTable('whups_version_seq');
        }

        $this->changeColumn('whups_queries', 'query_id', 'autoincrementKey');
        if (in_array('whups_queries_seq', $this->tables())) {
            $this->dropTable('whups_queries_seq');
        }
    }

    /**
     * Downgrade
     */
    public function down()
    {
        $this->changeColumn('whups_tickets', 'ticket_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_queues', 'queue_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_types', 'type_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_states', 'state_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_replies', 'reply_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_attributes_desc', 'attribute_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_comments', 'comment_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_logs', 'log_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_priorities', 'priority_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_versions', 'version_id', 'integer', ['null' => false]);
        $this->changeColumn('whups_queries', 'query_id', 'integer', ['null' => false]);
    }
}
