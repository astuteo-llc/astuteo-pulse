<?php
/**
 * Astuteo Pulse plugin for Craft CMS 3.x
 *
 * Pulse
 *
 * @link      https://astuteo.com
 * @copyright Copyright (c) 2020 Astuteo
 */

namespace astuteo\astuteopulse\console\controllers;

use astuteo\astuteopulse\AstuteoPulse;

use astuteo\astuteopulse\services\ReportStatusService;
use Craft;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Default Command
 *
 * @author    Astuteo
 * @package   AstuteoPulse
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Send site status to Astuteo's services to keep an eye on the site
     *
     * @return mixed
     */
    public function actionTakePulse()
    {
        ReportStatusService::makeReport();
        echo "Taking Pulse...\n";
        Craft::$app->queue->run();
        echo "♥ Heartbeat Taken!\n";
    }
}