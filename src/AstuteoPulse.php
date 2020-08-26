<?php
/**
 * Astuteo Pulse plugin for Craft CMS 3.x
 *
 * Connecting Astuteo client sites to our monitor.
 *
 * @link      https://astuteo.com
 * @copyright Copyright (c) 2020 Astuteo
 */

namespace astuteo\astuteopulse;

use Craft;
use craft\base\Plugin;
use astuteo\astuteopulse\services\ReportStatusService;
use astuteo\astuteopulse\services\BroadcastStatusService;

use yii\base\Event;

/**
 * @author    Astuteo
 * @package   AstuteoPulse
 * @since     1.0.0
 *
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class AstuteoPulse extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * AstuteoPulse::$plugin
     *
     * @var AstuteoPulse
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    public $schemaVersion = '1.0.6';
    public $hasCpSettings = false;
    public $hasCpSection = false;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app->request->getIsCpRequest()) {
            $this->_bindCpEvents();
        }
    }

    // Protected Methods
    // =========================================================================
    private function _bindCpEvents()
    {
        // Phone home for Airtable inventory
        if (!Craft::$app->request->getIsAjax() && !Craft::$app->config->general->devMode) {
            ReportStatusService::makeReport();
        }
    }

}
