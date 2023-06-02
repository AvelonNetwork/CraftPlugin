<?php

namespace avelonnetwork\craftavelon\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * Settings record
 */
class SettingsRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%avelon_settings}}';
    }
}
