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
 * Class BroadcastStatusService
 * 
 * Service for broadcasting system status information for Craft CMS installations.
 */
class BroadcastStatusService {
    private static string $_siteUrl;

    /**
     * Makes a system status report
     * 
     * @return void
     */
    public static function makeReport(): void
    {
        self::broadcastInfo();
    }

    /**
     * Checks if the request is authorized using the API key
     * 
     * @return bool True if authorized, false otherwise
     */
    public static function checkAuthorized(): bool
    {
        $sitekey = getenv('ASTUTEO_API_KEY');
        $requestkey = Craft::$app->request->getParam('key');

        return $requestkey !== '' && $sitekey === $requestkey;
    }

    /**
     * Broadcasts system information including version, updates, and plugin status
     * 
     * @return string JSON encoded response with status and data
     */
    public static function broadcastInfo(): string
    {
        Craft::$app->updates->getUpdates(1);
        if(!self::checkAuthorized()) {
            return json_encode([
                'status' => 'error',
                'message' => 'Unauthorized access',
                'data' => null
            ]);
        }
        self::$_siteUrl = UrlHelper::siteUrl('/');
        $siteInfo = [
            'status' => 'success',
            'message' => 'System information retrieved successfully',
            'data' => [
                'this' => 'sample',
                'url' => self::$_siteUrl,
                'name' => Craft::$app->getSystemName(),
                'system' => 'Craft',
                'systemEdition' => self::_getEdition(),
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
            ]
        ];
        return json_encode($siteInfo);
    }

    /**
     * Gets the database driver name and version
     * 
     * @return string Database driver name and version
     */
    private static function _dbDriver(): string
    {
        $db = Craft::$app->getDb();
        $driverName = $db->getIsMysql() ? 'MySQL' : 'PostgreSQL';
        return $driverName . ' ' . App::normalizeVersion($db->getSchema()->getServerVersion());
    }

    /**
     * Gets the contents of package.json if it exists
     * 
     * @return string Contents of package.json or empty string if file doesn't exist
     */
    private static function _packageJson(): string
    {
        $file = self::_basePath() . 'package.json';
        return file_exists($file) ? file_get_contents($file) : '';
    }

    /**
     * Gets todo files content from various markdown files
     * 
     * @return string JSON encoded array of todo contents
     */
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

    /**
     * Gets the base path for the application
     * 
     * @return string Base path of the application
     */
    private static function _basePath(): string
    {
        return Craft::$app->config->configDir . '/../';
    }

    /**
     * Gets a formatted string of all installed plugins and their versions
     * 
     * @return string Newline-separated list of plugins and their versions
     */
    private static function _plugins(): string
    {
        $plugins = Craft::$app->plugins->getAllPlugins();
        return implode(PHP_EOL, array_map(
            fn($plugin) => "{$plugin->name} ({$plugin->developer}): {$plugin->version}",
            $plugins
        ));
    }

    /**
     * Gets detailed information about all installed plugins
     * 
     * @return array Array of plugin information
     */
    private static function _getAllPluginInfo(): array 
    {
        return Craft::$app->plugins->getAllPluginInfo();
    }

    /**
     * Gets the current timestamp in m/d/Y format
     * 
     * @return string Formatted date string or empty string on error
     */
    private static function _timestamp(): string 
    {
        try {
            return DateTimeHelper::toDateTime(DateTimeHelper::currentTimeStamp())->format('m/d/Y');
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Checks if there are any critical updates available
     * 
     * @return bool True if critical updates are available
     */
    private static function _criticalUpdate(): bool 
    {
        return Craft::$app->getUpdates()->getIsCriticalUpdateAvailable();
    }

    /**
     * Gets a list of plugins with license issues
     * 
     * @return string Newline-separated list of plugins with issues
     */
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

    /**
     * Gets the number of available updates
     * 
     * @return string Number of updates or 'Up-to-date' if none available
     */
    private static function _updates(): string 
    {
        $totalUpdates = Craft::$app->getUpdates()->getTotalAvailableUpdates();
        return $totalUpdates === 0 ? 'Up-to-date' : (string)$totalUpdates;
    }

    /**
     * Gets the total number of deprecation notices
     * 
     * @return string Number of deprecation notices
     */
    private static function _deprecations(): string
    {
        return Craft::$app->getDeprecator()->getTotalLogs();
    }

    /**
     * Gets a list of all installed modules
     * 
     * @return string Newline-separated list of module class names
     */
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

    /**
     * Gets the system version information
     * 
     * @return string System version information or 'Unknown Version' on error
     */
    private static function _getSystemVersion(): string
    {
        try {
            return Craft::$app->getVersion();
        } catch (Exception) {
            return 'Unknown Version';
        }
    }

    /*
     * Gets the Craft edition name
     *
     * @return string Human friendly edition name or 'Unknown Edition'
     */
    private static function _getEdition(): string {
        try {
            // Check if we're using Craft 5 (has name property)
            if (property_exists(Craft::$app->edition, 'name')) {
                return (string)Craft::$app->edition->name;
            }
            // Fall back to Craft 4 method
            return Craft::$app->edition->getEditionName();
        } catch (Exception) {
            return 'Unknown Edition';
        }
    }
}
