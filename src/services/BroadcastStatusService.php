<?php

namespace astuteo\astuteopulse\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\base\PluginInterface;
use Exception;
use yii\base\Module;
use craft\base\ApplicationTrait;

/**
 * Class ReportStatusService
 */
class BroadcastStatusService {
    private static string $_siteUrl;

    public static function makeReport(): void
    {
        self::broadcastInfo();
    }

    public static function checkAuthorized(): bool
    {
        $sitekey = getenv('ASTUTEO_API_KEY');
        $requestkey = Craft::$app->request->getParam('key');

        return $requestkey !== '' && $sitekey === $requestkey;
    }

    public static function broadcastInfo(): bool|string
    {
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
            'systemVersion' => self::_getSystemVersion(),
            'lastChecked' => self::_timestamp(),
            'phpVersion' => App::phpVersion(),
            'dbVersion' => self::_dbDriver(),
            'updates' => self::_updates(),
            'criticalUpdate' => self::_criticalUpdate(),
            'modules' => self::_modules(),
            'deprecationNotices' => self::_deprecations(),
            'pluginsText' => self::_plugins(),
            'pluginsArray' => self::_getAllPluginInfo(),
            'pluginIssues' => self::_licenseIssues(),
            'packageJson' => self::_packageJson(),
            'todos' => self::_todos(),
        ];
        return json_encode($siteInfo);
    }

    private static function _dbDriver(): string
    {
        $db = Craft::$app->getDb();
        $driverName = $db->getIsMysql() ? 'MySQL' : 'PostgreSQL';
        return $driverName . ' ' . App::normalizeVersion($db->getSchema()->getServerVersion());
    }

    private static function _packageJson(): string
    {
        $file = self::_basePath() . 'package.json';
        return file_exists($file) ? file_get_contents($file) : '';
    }

    private static function _todos(): string
    {
        $base = self::_basePath();
        $todoFiles = [
            'js' => $base . 'todo-javascript.md',
            'css' => $base . 'todo-styles.md',
            'templates' => $base . 'todo-templates.md'
        ];

        $todos = [];
        foreach ($todoFiles as $type => $file) {
            if (file_exists($file)) {
                $todos[] = [$type => file_get_contents($file)];
            }
        }
        return json_encode($todos);
    }

    private static function _basePath(): string
    {
        return Craft::$app->config->configDir . '/../';
    }

    private static function _plugins(): string
    {
        $plugins = Craft::$app->plugins->getAllPlugins();
        return implode(PHP_EOL, array_map(
            fn($plugin) => "{$plugin->name} ({$plugin->developer}): {$plugin->version}",
            $plugins
        ));
    }

    private static function _getAllPluginInfo(): array 
    {
        return Craft::$app->plugins->getAllPluginInfo();
    }

    private static function _timestamp(): string 
    {
        try {
            return DateTimeHelper::toDateTime(DateTimeHelper::currentTimeStamp())->format('m/d/Y');
        } catch (Exception) {
            return '';
        }
    }

    private static function _criticalUpdate(): bool 
    {
        return Craft::$app->getUpdates()->getIsCriticalUpdateAvailable();
    }

    private static function _licenseIssues(): string 
    {
        $pluginsService = Craft::$app->getPlugins();
        $issuePlugins = array_filter(
            $pluginsService->getAllPlugins(),
            fn($plugin, $handle) => $pluginsService->hasIssues($handle),
            ARRAY_FILTER_USE_BOTH
        );

        return implode(PHP_EOL, array_map(
            fn($plugin) => "{$plugin->name} | ",
            $issuePlugins
        ));
    }

    private static function _updates(): string 
    {
        $totalUpdates = Craft::$app->getUpdates()->getTotalAvailableUpdates();
        return $totalUpdates === 0 ? 'Up-to-date' : (string)$totalUpdates;
    }
    
    private static function _deprecations(): string
    {
        return Craft::$app->getDeprecator()->getTotalLogs();
    }

    private static function _modules(): string
    {
        $modules = [];
        foreach (Craft::$app->getModules() as $id => $module) {
            if ($module instanceof PluginInterface) {
                continue;
            }
            
            $modules[$id] = match(true) {
                $module instanceof Module => get_class($module),
                is_string($module) => $module,
                is_array($module) && isset($module['class']) => $module['class'],
                default => null
            };
        }

        return implode(PHP_EOL, array_filter($modules));
    }

    private static function _getSystemVersion(): string
    {
        try {
            return (string)Craft::$app->edition->value . ' ' . Craft::$app->getVersion();
        } catch (Exception) {
            return 'Unknown Version';
        }
    }
}
