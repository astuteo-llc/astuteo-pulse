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

    private const FIELDS = [
        'reboot_pending',
        'reboot_pending_since',
        'auto_updates_enabled',
        'last_check_at',
        'host_id',
    ];

    public const REASON_NOTIFIER_ABSENT = 'reboot-notifier-absent';
    public const REASON_MISSING = 'source-missing';
    public const REASON_UNREADABLE = 'source-unreadable';
    public const REASON_UNPARSEABLE = 'config-unparseable';
    public const REASON_EMPTY = 'machine-id-empty';
    public const REASON_INVALID = 'machine-id-invalid';
    public const REASON_READER_FAILED = 'reader-failed';

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
     *     unknown: list<array{field: string, reason: string}>
     * }
     */
    public function toArray(): array
    {
        $this->unknown = [];

        [$pending, $since] = $this->reboot();
        $autoUpdates = $this->autoUpdatesEnabled();
        $lastCheck = $this->lastCheckAt();
        $hostId = $this->hostId();

        return [
            'reboot_pending' => $pending,
            'reboot_pending_since' => $since,
            'auto_updates_enabled' => $autoUpdates,
            'last_check_at' => $lastCheck,
            'host_id' => $hostId,
            'unknown' => $this->unknownRecords(),
        ];
    }

    /**
     * The all-unknown shape, for callers that could not run a read at all.
     *
     * @return array<string, mixed>
     */
    public static function unavailable(): array
    {
        $host = array_fill_keys(self::FIELDS, null);
        $host['unknown'] = array_map(
            static fn(string $field): array => ['field' => $field, 'reason' => self::REASON_READER_FAILED],
            self::FIELDS
        );

        return $host;
    }

    /**
     * @return array{0: bool|null, 1: string|null}
     */
    private function reboot(): array
    {
        // The flag only ever appears if a postinst called the notifier helper. Without the helper
        // installed the host can never raise it, so a missing flag says nothing about reboot state.
        if (!@file_exists($this->path(self::NOTIFIER_HELPER))) {
            $this->markUnknown(['reboot_pending', 'reboot_pending_since'], self::REASON_NOTIFIER_ABSENT);

            return [null, null];
        }

        $flag = $this->path(self::REBOOT_REQUIRED);

        if (!@file_exists($flag)) {
            // An absent flag only means "no reboot" if the directory was actually searchable.
            if (!@is_dir(dirname($flag)) || !@is_executable(dirname($flag))) {
                $this->markUnknown(['reboot_pending', 'reboot_pending_since'], self::REASON_UNREADABLE);

                return [null, null];
            }

            return [false, null];
        }

        $timestamp = @filemtime($flag);

        if ($timestamp === false) {
            $this->unknown['reboot_pending_since'] = self::REASON_UNREADABLE;

            return [true, null];
        }

        return [true, gmdate('c', $timestamp)];
    }

    private function autoUpdatesEnabled(): ?bool
    {
        $file = $this->path(self::AUTO_UPGRADES);

        if (!@file_exists($file)) {
            $this->unknown['auto_updates_enabled'] = self::REASON_MISSING;

            return null;
        }

        $contents = @file_get_contents($file);

        if ($contents === false) {
            $this->unknown['auto_updates_enabled'] = self::REASON_UNREADABLE;

            return null;
        }

        $active = preg_replace(['#/\*.*?\*/#s', '/^\s*(?:\/\/|#).*$/m'], '', $contents) ?? '';

        if (!preg_match_all('/APT::Periodic::Unattended-Upgrade\s+"(\d+)"/i', $active, $matches)) {
            $this->unknown['auto_updates_enabled'] = self::REASON_UNPARSEABLE;

            return null;
        }

        // apt merges apt.conf.d in lexical order and the last assignment wins.
        return (int)end($matches[1]) !== 0;
    }

    /**
     * Both sources are stat-only. updates-available holds the human readable apt-check output,
     * including update counts and ESM status, so reading its contents would breach the no-counts rule.
     */
    private function lastCheckAt(): ?string
    {
        $sourceSeen = false;

        foreach ([self::SUCCESS_STAMP, self::UPDATES_AVAILABLE] as $relative) {
            $file = $this->path($relative);

            if (!@file_exists($file)) {
                continue;
            }

            $sourceSeen = true;
            $timestamp = @filemtime($file);

            if ($timestamp !== false) {
                return gmdate('c', $timestamp);
            }
        }

        $this->unknown['last_check_at'] = $sourceSeen ? self::REASON_UNREADABLE : self::REASON_MISSING;

        return null;
    }

    /**
     * systemd requires the machine ID be hashed with an application key before leaving the host,
     * so the raw value is never emitted and cannot be recovered from what is.
     */
    private function hostId(): ?string
    {
        $file = $this->path(self::MACHINE_ID);

        if (!@file_exists($file)) {
            $this->unknown['host_id'] = self::REASON_MISSING;

            return null;
        }

        $contents = @file_get_contents($file);

        if ($contents === false) {
            $this->unknown['host_id'] = self::REASON_UNREADABLE;

            return null;
        }

        $machineId = trim($contents);

        if ($machineId === '') {
            $this->unknown['host_id'] = self::REASON_EMPTY;

            return null;
        }

        // Rejects systemd's "uninitialized" sentinel, which would otherwise collapse every
        // first-boot host in the fleet onto one identifier.
        if (!preg_match('/^[0-9a-f]{32}$/', $machineId)) {
            $this->unknown['host_id'] = self::REASON_INVALID;

            return null;
        }

        return hash_hmac('sha256', $machineId, self::HOST_ID_KEY);
    }

    /**
     * A list, not a map, so the JSON type is the same whether or not anything is unknown.
     *
     * @return list<array{field: string, reason: string}>
     */
    private function unknownRecords(): array
    {
        $records = [];

        foreach (self::FIELDS as $field) {
            if (isset($this->unknown[$field])) {
                $records[] = ['field' => $field, 'reason' => $this->unknown[$field]];
            }
        }

        return $records;
    }

    /**
     * @param list<string> $fields
     */
    private function markUnknown(array $fields, string $reason): void
    {
        foreach ($fields as $field) {
            $this->unknown[$field] = $reason;
        }
    }

    private function path(string $relative): string
    {
        return $this->root . $relative;
    }
}
