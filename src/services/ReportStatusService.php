<?php

namespace astuteo\astuteopulse\services;

use astuteo\astuteopulse\ReportJob;
use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\base\PluginInterface;
use Exception;
use yii\base\Module;
use TANIOS\Airtable\Airtable;

/**
 * Class ReportStatusService
 */
class ReportStatusService {
    // Also borrowing this from Viget
    // https://github.com/vigetlabs/craft-viget-base/
    /**
     * @var
     */
    private static $_cacheKey;
    private static $_siteUrl;
    private static $_airtable;
    private static $_tableName;

    /**
     * If we haven't checked within a day, push a new job to the queue
     */
    public static function makeReport()
    {
        // We've checked recently enough, bail out
        if (Craft::$app->cache->get(self::$_cacheKey) !== false) return;
        Craft::$app->queue->push(new ReportJob());
    }

    /**
     * Create new record or updating existing record in Airtable
     */
    public static function makeRequest()
    {
        $key = getenv('AIRTABLE_API_KEY');
        $base = getenv('AIRTABLE_BASE');

        // Missing environment values, do nothing
        if ($key === false || $base === false) return;

        self::$_siteUrl = UrlHelper::siteUrl('/');
        self::$_airtable = new Airtable([
            'api_key' => $key,
            'base'    => $base,
        ]);
        self::$_tableName = 'Inventory';

        $existingRecord = self::_existingRecord();

        try {
            if ($existingRecord === null) {
                $request = self::$_airtable->saveContent(self::$_tableName, self::_appInfo());
            } else {
                $request = self::$_airtable->updateContent(self::$_tableName, [
                    [
                        'id' => $existingRecord,
                        'fields' => self::_appInfo(),
                    ],
                ]);
            }
        } catch (Exception $e) {
            Craft::error(
                'Error reporting',
                __METHOD__
            );
        }

        $oneDayDuration = 60 * 60 * 24;
        Craft::$app->cache->set(self::$_cacheKey, true, $oneDayDuration);
    }

    /**
     * Return the existing record's ID (if one exists)
     *
     * @return string|null
     */
    private static function _existingRecord(): ?string
    {
        $request = self::$_airtable->quickCheck(self::$_tableName, 'Site URL', self::$_siteUrl);

        // No existing record
        if ($request->count === 0) return null;

        return $request->records['0']->id ?? null;
    }

    /**
     * Returns all the info we want to send to Airtable
     *
     * @return array
     */
    private static function _appInfo(): array
    {
        return [
            'Site URL' => self::$_siteUrl,
            'Site Name' => Craft::$app->getSystemName(),
            'Craft Version' => 'Craft ' . App::editionName(Craft::$app->getEdition()) . ' ' . Craft::$app->getVersion(),
            'PHP Version' => App::phpVersion(),
            'DB Version' => self::_dbDriver(),
            'Plugins' => self::_plugins(),
            'Updates' => self::_updates(),
            'Modules' => self::_modules(),
            'Deprecation Notices' => self::_deprecations(),
        ];
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



    private static function _updates(): string {
        $message = 'Up-to-date';
        $totalupdates = Craft::$app->getUpdates()->getTotalAvailableUpdates();
        if($totalupdates != 0) {
            $message = $totalupdates . ' Updates';
        }
        if(Craft::$app->getUpdates()->getIsCriticalUpdateAvailable()) {
            $message = 'CRITICAL UPDATE ' . $message;
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