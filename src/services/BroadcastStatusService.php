<?php

namespace astuteo\astuteopulse\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\base\PluginInterface;
use Exception;
use yii\base\Module;

/**
 * Class ReportStatusService
 */
class BroadcastStatusService {
    // Also borrowing this from Viget
    // https://github.com/vigetlabs/craft-viget-base/
    /**
     * @var
     */
    private static $_cacheKey;
    private static $_siteUrl;

    /**
     * If we haven't checked within a day, push a new job to the queue
     */
    public static function makeReport()
    {
        // We've checked recently enough, bail out
//        if (Craft::$app->cache->get(self::$_cacheKey) !== false) return;
        self::broadcastInfo();
    }

    public static function checkAuthorized() {
        $sitekey = getenv('ASTUTEO_API_KEY');
        $requestkey = Craft::$app->request->getParam('key');

        if($requestkey === '') {
            return false;
        }
        if($sitekey === $requestkey) {
            return true;
        }
        return false;
    }


    public static function broadcastInfo() {
        Craft::$app->updates->getUpdates(1);
        if(!self::checkAuthorized()) {
            return false;
        }
        self::$_siteUrl = UrlHelper::siteUrl('/');
        $siteInfo[] = [
            'this' => 'sample',
            'url' => self::$_siteUrl,
            'name' => Craft::$app->getSystemName(),
            'system' => 'Craft',
            'systemVersion' => App::editionName(Craft::$app->getEdition()) . ' ' . Craft::$app->getVersion(),
            'lastChecked' => self::_timestamp(),
            'phpVersion' => App::phpVersion(),
            'dbVersion' => self::_dbDriver(),
            'updates' => self::_updates(),
            'criticalUpdate' => self::_criticalUpdate(),
            'modules' => self::_modules(),
            'deprecationNotices' => self::_deprecations(),
            'pluginsText' => self::_plugins(),
            'pluginsArray' => self::_pluginsArray(),
        ];
        $oneDayDuration = 60 * 60 * 24;
        Craft::$app->cache->set(self::$_cacheKey, true, $oneDayDuration);
        return json_encode($siteInfo);
    }


    /**
     * Returns the DB driver name and version
     *
     * @return string
     */
    private static function _dbDriver(): string
    {
        $db = Craft::$app->getDb();

        if ($db->getIsMysql()) {
            $driverName = 'MySQL';
        } else {
            $driverName = 'PostgreSQL';
        }

        return $driverName . ' ' . App::normalizeVersion($db->getSchema()->getServerVersion());
    }

    /**
     * Returns the list of plugins and versions
     *
     * @return string
     */
    private static function _plugins(): string
    {
        $plugins = Craft::$app->plugins->getAllPlugins();

        return implode(PHP_EOL, array_map(function($plugin) {
            return "{$plugin->name} ({$plugin->developer}): {$plugin->version}";
        }, $plugins));
    }




    private static function _pluginLicenseIssues($handle): string {
        $issues = Craft::$app->plugins->getLicenseIssues($handle);

        return implode(PHP_EOL, array_map(function($issue) {
            return "{$issue}";
        },  $issues));
    }

    /**
     * Returns the list of plugins and versions
     *
     * @return json
     */
    private static function _pluginsArray(): array
    {
        $plugins = Craft::$app->plugins->getAllPlugins();

        $pluginsArray = [];

        foreach ($plugins as $plugin) {
            $thisPlugin = [
              'name' => $plugin->name,
              'developer' => $plugin->developer,
              'handle' => $plugin->handle,
              'version' => $plugin->version,
              'description' => $plugin->description,
              'licenseIssues' => self::_pluginLicenseIssues($plugin->handle)
            ];
            $pluginsArray = array_merge($pluginsArray, [$thisPlugin]);
        }

//        var_dump($pluginsArray);
//        var_dump($pluginsArray);
        return $pluginsArray;
    }


    private static function _timestamp(): string {
        try {
            $current = DateTimeHelper::toDateTime(DateTimeHelper::currentTimeStamp());
        } catch (Exception $e) {
            return '';
        }
        return $current->format('m/d/Y');
    }


    private static function _criticalUpdate(): bool {
        if(Craft::$app->getUpdates()->getIsCriticalUpdateAvailable()) {
            return true;
        } else {
            return false;
        }
    }

    private static function _updates(): string {
        $message = 'Up-to-date';
        $updates =  Craft::$app->getUpdates();
        $totalupdates = $updates->getTotalAvailableUpdates();
        if($totalupdates != 0) {
            $message = $totalupdates;
        }
        return $message;
    }

    /**
     * Returns Deprecation count
     * @return string
     */

    private static function _deprecations(): string
    {
        return Craft::$app->getDeprecator()->getTotalLogs();
    }

    /**
     * Returns the list of modules
     *
     * @return string
     */
    private static function _modules(): string
    {
        $modules = [];
        foreach (Craft::$app->getModules() as $id => $module) {
            if ($module instanceof PluginInterface) {
                continue;
            }
            if ($module instanceof Module) {
                $modules[$id] = get_class($module);
            } else if (is_string($module)) {
                $modules[$id] = $module;
            } else if (is_array($module) && isset($module['class'])) {
                $modules[$id] = $module['class'];
            }
        }

        return implode(PHP_EOL, $modules);
    }
}
