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

use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
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

    public static $plugin;
    public string $schemaVersion = '4.0.0';
    public bool $hasCpSettings = false;
    public bool $hasCpSection = false;

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['astuteo-pulse'] = 'astuteo-pulse/default/index';
                $event->rules['astuteo-pulse/json'] = 'astuteo-pulse/default/json';
            }
        );
    }
}
