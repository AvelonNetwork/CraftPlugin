<?php

namespace avelonnetwork\craftavelon\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{

    /**
     * @var string The database driver to use
     */
    public $driver;


    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;

        $createTableResp = $this->createTable(
            '{{%avelon_settings}}',
            [
                'handle' => $this->string()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[handle]])',
                // Custom columns in the table
                'accountId' => $this->string(255)->notNull(),
                'bearerToken' => $this->string(255)->notNull(),
            ]
        );

        if ($createTableResp) {
            Craft::$app->db->schema->refresh();
        }


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%avelon_settings}}');

        return true;
    }
}
