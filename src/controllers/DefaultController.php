<?php
/**
 * Pulse Receiver plugin for Craft CMS 3.x
 *
 * Internal
 *
 * @link      astuteo.com
 * @copyright Copyright (c) 2020 Astuteo
 */

namespace astuteo\astuteopulse\controllers;

use astuteo\astuteopulse\services\BroadcastStatusService;

use craft\web\Controller;

class DefaultController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index', 'do-something'];
    public function actionIndex(): bool|string
    {
        return BroadcastStatusService::broadcastInfo();
    }

}
