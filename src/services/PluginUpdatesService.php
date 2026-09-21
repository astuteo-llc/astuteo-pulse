<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\services;

use craft\models\Updates as UpdatesModel;

/**
 * Reports plugin version updates from Craft's update check.
 *
 * Distinct from `upgradeAvailable` in plugin info, which Craft sets for edition and trial
 * upgrades and which says nothing about whether a newer release exists.
 */
class PluginUpdatesService
{
    /**
     * @param bool $checked Whether the update check returned data. Craft caches an empty result
     *     when its API call fails, which must not read as every plugin being current.
     * @return list<array{handle: string, latestVersion: ?string, releases: int, critical: bool, status: string}>|null
     *     Plugins with a newer release, or null when the check did not return data.
     */
    public static function describe(UpdatesModel $updates, bool $checked): ?array
    {
        if (!$checked) {
            return null;
        }

        $described = [];

        foreach ($updates->plugins as $handle => $update) {
            if (!$update->getHasReleases()) {
                continue;
            }

            $described[] = [
                'handle' => (string)$handle,
                'latestVersion' => $update->getLatest()?->version,
                'releases' => count($update->releases),
                'critical' => $update->getHasCritical(),
                'status' => $update->status,
            ];
        }

        return $described;
    }
}
