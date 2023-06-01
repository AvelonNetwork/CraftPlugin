<?php

namespace avelonnetwork\craftavelon\models;

use Craft;
use craft\base\Model;

/**
 * Avelon settings
 */
class Settings extends Model
{

    public string $accountId = '';
    public string $bearerToken = '';

    public function defineRules(): array
    {
        return [
            [['accountId', 'bearerToken'], 'required'],
        ];
    }
}
