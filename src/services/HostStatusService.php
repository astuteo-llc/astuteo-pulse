<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\services;

/**
 * Reports host reboot state from files the web user can already read.
 *
 * Every value is a boolean, timestamp or opaque identifier. No version string, package name
 * or count is ever emitted, so a leaked API key discloses nothing matchable to a vulnerability.
 */
class HostStatusService
{
    private const NOTIFIER_HELPER = 'usr/share/update-notifier/notify-reboot-required';
    private const REBOOT_REQUIRED = 'var/run/reboot-required';
    private const AUTO_UPGRADES = 'etc/apt/apt.conf.d/20auto-upgrades';
    private const SUCCESS_STAMP = 'var/lib/apt/periodic/update-success-stamp';
    private const UPDATES_AVAILABLE = 'var/lib/update-notifier/updates-available';
    private const MACHINE_ID = 'etc/machine-id';

    private const REASON_NOTIFIER_ABSENT = 'reboot-notifier-absent';
    private const REASON_MISSING = 'source-missing';
    private const REASON_UNREADABLE = 'source-unreadable';
    private const REASON_UNPARSEABLE = 'config-unparseable';
    private const REASON_EMPTY = 'machine-id-empty';

    /** Fixed and public by design: sites on one server must derive the same identifier. */
    private const HOST_ID_KEY = 'astuteo-pulse.host-id.v1';

    private string $root;

    /** @var array<string, string> */
    private array $unknown = [];

    public function __construct(string $root = '/')
    {
        $this->root = rtrim($root, '/') . '/';
    }

    /**
     * @return array{
     *     reboot_pending: bool|null,
     *     reboot_pending_since: string|null,
     *     auto_updates_enabled: bool|null,
     *     last_check_at: string|null,
     *     host_id: string|null,
     *     unknown: array<string, string>
     * }
     */
    public function toArray(): array
    {
        $this->unknown = [];

        [$pending, $since] = $this->reboot();

        return [
            'reboot_pending' => $pending,
            'reboot_pending_since' => $since,
            'auto_updates_enabled' => $this->autoUpdatesEnabled(),
            'last_check_at' => $this->lastCheckAt(),
            'host_id' => $this->hostId(),
            'unknown' => $this->unknown,
        ];
    }

    /**
     * The all-unknown shape, for callers that could not run a read at all.
     *
     * @return array<string, mixed>
     */
    public static function unavailable(string $reason): array
    {
        return [
            'reboot_pending' => null,
            'reboot_pending_since' => null,
            'auto_updates_enabled' => null,
            'last_check_at' => null,
            'host_id' => null,
            'unknown' => [
                'reboot_pending' => $reason,
                'reboot_pending_since' => $reason,
                'auto_updates_enabled' => $reason,
                'last_check_at' => $reason,
                'host_id' => $reason,
            ],
        ];
    }

    /**
     * @return array{0: bool|null, 1: string|null}
     */
    private function reboot(): array
    {
        // The flag only ever appears if a postinst called the notifier helper. Without the helper
        // installed the host can never raise it, so a missing flag says nothing about reboot state.
        if (!file_exists($this->path(self::NOTIFIER_HELPER))) {
            $this->unknown['reboot_pending'] = self::REASON_NOTIFIER_ABSENT;
            $this->unknown['reboot_pending_since'] = self::REASON_NOTIFIER_ABSENT;

            return [null, null];
        }

        $flag = $this->path(self::REBOOT_REQUIRED);

        if (!file_exists($flag)) {
            return [false, null];
        }

        $since = $this->modifiedAt($flag);

        if ($since === null) {
            $this->unknown['reboot_pending_since'] = self::REASON_UNREADABLE;
        }

        return [true, $since];
    }

    private function autoUpdatesEnabled(): ?bool
    {
        $file = $this->path(self::AUTO_UPGRADES);

        if (!file_exists($file)) {
            $this->unknown['auto_updates_enabled'] = self::REASON_MISSING;

            return null;
        }

        if (!is_readable($file)) {
            $this->unknown['auto_updates_enabled'] = self::REASON_UNREADABLE;

            return null;
        }

        $contents = @file_get_contents($file);

        if ($contents === false) {
            $this->unknown['auto_updates_enabled'] = self::REASON_UNREADABLE;

            return null;
        }

        if (!preg_match('/APT::Periodic::Unattended-Upgrade\s+"(\d+)"/i', $contents, $matches)) {
            $this->unknown['auto_updates_enabled'] = self::REASON_UNPARSEABLE;

            return null;
        }

        return $matches[1] !== '0';
    }

    /**
     * Both sources are stat-only. updates-available holds the human readable apt-check output,
     * including update counts and ESM status, so reading its contents would breach the no-counts rule.
     */
    private function lastCheckAt(): ?string
    {
        foreach ([self::SUCCESS_STAMP, self::UPDATES_AVAILABLE] as $relative) {
            $timestamp = $this->modifiedAt($this->path($relative));

            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        $this->unknown['last_check_at'] = self::REASON_MISSING;

        return null;
    }

    /**
     * systemd requires the machine ID be hashed with an application key before leaving the host,
     * so the raw value is never emitted and cannot be recovered from what is.
     */
    private function hostId(): ?string
    {
        $file = $this->path(self::MACHINE_ID);

        if (!file_exists($file)) {
            $this->unknown['host_id'] = self::REASON_MISSING;

            return null;
        }

        if (!is_readable($file)) {
            $this->unknown['host_id'] = self::REASON_UNREADABLE;

            return null;
        }

        $machineId = @file_get_contents($file);

        if ($machineId === false) {
            $this->unknown['host_id'] = self::REASON_UNREADABLE;

            return null;
        }

        $machineId = trim($machineId);

        if ($machineId === '') {
            $this->unknown['host_id'] = self::REASON_EMPTY;

            return null;
        }

        return hash_hmac('sha256', $machineId, self::HOST_ID_KEY);
    }

    private function modifiedAt(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }

        $timestamp = @filemtime($file);

        return $timestamp === false ? null : gmdate('c', $timestamp);
    }

    private function path(string $relative): string
    {
        return $this->root . $relative;
    }
}
