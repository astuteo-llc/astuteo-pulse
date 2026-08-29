<?php

namespace astuteo\astuteopulse\services;

/**
 * Read host patch state from sources available to the web user.
 *
 * An unreadable source yields a null value with a reason, never a healthy default.
 * A reason beside a non-null value means that value was inferred, not confirmed.
 */
class HostStatusService
{
    private const REBOOT_FLAG = '/var/run/reboot-required';
    private const APT_CONF_DIR = '/etc/apt/apt.conf.d';
    private const MACHINE_ID = '/etc/machine-id';

    // Ordered by how directly each marks a completed update check.
    private const UPDATE_CHECK_SOURCES = [
        'apt_periodic' => '/var/lib/apt/periodic/update-success-stamp',
        'update_notifier' => '/var/lib/update-notifier/updates-available',
        'apt_lists' => '/var/lib/apt/lists',
    ];

    /**
     * @return array<string, array{value: mixed, reason?: string, since?: string, durationSeconds?: int, source?: string}>
     */
    public static function get(): array
    {
        $producers = [
            'id' => fn() => self::_hostId(),
            'rebootPending' => fn() => self::_rebootPending(),
            'autoUpdates' => fn() => self::_autoUpdates(),
            'lastUpdateCheck' => fn() => self::_lastUpdateCheck(),
        ];

        $host = [];
        foreach ($producers as $key => $producer) {
            try {
                $host[$key] = $producer();
            } catch (\Throwable) {
                $host[$key] = self::_unknown('read_failed');
            }
        }

        return $host;
    }

    private static function _hostId(): array
    {
        if (!is_readable(self::MACHINE_ID)) {
            return self::_unknown(file_exists(self::MACHINE_ID) ? 'source_unreadable' : 'source_missing');
        }

        $id = trim((string)@file_get_contents(self::MACHINE_ID));
        if ($id === '') {
            return self::_unknown('source_unparseable');
        }

        return self::_field(substr(hash('sha256', $id), 0, 32)); // systemd treats machine-id as confidential
    }

    private static function _rebootPending(): array
    {
        $dir = dirname(self::REBOOT_FLAG);
        if (!is_dir($dir) || !is_readable($dir)) {
            return self::_unknown('source_unreadable');
        }

        if (file_exists(self::REBOOT_FLAG)) {
            $field = self::_field(true);
            $since = @filemtime(self::REBOOT_FLAG);
            if ($since !== false) {
                $field['since'] = date(DATE_ATOM, $since); // /var/run is tmpfs cleared on boot, so mtime is when it became pending
                $field['durationSeconds'] = max(0, time() - $since); // computed here; the monitor has its own clock
            }

            return $field;
        }

        // Without apt's update tooling nothing writes the flag, so absence proves nothing.
        if (!self::_aptConfigReadable()) {
            return self::_unknown('mechanism_unconfirmed');
        }

        return self::_field(false);
    }

    private static function _autoUpdates(): array
    {
        if (!self::_aptConfigReadable()) {
            return self::_unknown('source_unreadable');
        }

        $files = glob(self::APT_CONF_DIR . '/*');
        if ($files === false) {
            return self::_unknown('source_unreadable');
        }
        sort($files);

        $enabled = null;
        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            // Later files in apt.conf.d override earlier ones, so the last match wins.
            if (preg_match_all('/^\s*APT::Periodic::Unattended-Upgrade\s+"(\d+)"/mi', $contents, $matches)) {
                $enabled = end($matches[1]) !== '0';
            }
        }

        if ($enabled === null) {
            return self::_field(false, 'source_missing');
        }

        return self::_field($enabled);
    }

    private static function _lastUpdateCheck(): array
    {
        foreach (self::UPDATE_CHECK_SOURCES as $name => $path) {
            if (!file_exists($path) || !is_readable($path)) {
                continue;
            }

            $mtime = @filemtime($path);
            if ($mtime === false) {
                continue;
            }

            $field = self::_field(date(DATE_ATOM, $mtime));
            $field['source'] = $name;
            return $field;
        }

        return self::_unknown('source_missing');
    }

    private static function _aptConfigReadable(): bool
    {
        return is_dir(self::APT_CONF_DIR) && is_readable(self::APT_CONF_DIR);
    }

    private static function _field(mixed $value, ?string $reason = null): array
    {
        $field = ['value' => $value];
        if ($reason !== null) {
            $field['reason'] = $reason;
        }

        return $field;
    }

    private static function _unknown(string $reason): array
    {
        return ['value' => null, 'reason' => $reason];
    }
}
