<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests\support;

use RuntimeException;

/**
 * Builds a throwaway filesystem tree shaped like the host paths HostStatusService reads.
 *
 * Built at runtime rather than committed under tests/fixtures because git does not preserve
 * modification times, and every freshness assertion here depends on them.
 */
final class HostFixture
{
    public const MACHINE_ID = '2f8a1c4e9b7d43a6851fe0c37d92b4aa';

    private string $root;

    private function __construct(string $root)
    {
        $this->root = $root;
    }

    public static function create(): self
    {
        $root = sys_get_temp_dir() . '/astuteo-pulse-fixture-' . bin2hex(random_bytes(8));

        if (!mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException("Could not create fixture root at {$root}");
        }

        return new self($root);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function withNotifierHelper(): self
    {
        return $this->write('usr/share/update-notifier/notify-reboot-required', "#!/bin/sh\n");
    }

    public function withRebootRequired(?int $mtime = null): self
    {
        return $this->write('var/run/reboot-required', '', $mtime);
    }

    public function withRebootRequiredPkgs(string $contents): self
    {
        return $this->write('var/run/reboot-required.pkgs', $contents);
    }

    public function withAutoUpgrades(bool $enabled): self
    {
        $flag = $enabled ? '1' : '0';

        return $this->withRawAutoUpgrades(
            "APT::Periodic::Update-Package-Lists \"{$flag}\";\n"
            . "APT::Periodic::Unattended-Upgrade \"{$flag}\";\n"
        );
    }

    public function withRawAutoUpgrades(string $contents): self
    {
        return $this->write('etc/apt/apt.conf.d/20auto-upgrades', $contents);
    }

    public function withUpdateSuccessStamp(int $mtime): self
    {
        return $this->write('var/lib/apt/periodic/update-success-stamp', '', $mtime);
    }

    /** Contents carry update counts and ESM status, so the service must only ever stat this. */
    public function withUpdatesAvailable(int $mtime): self
    {
        return $this->write(
            'var/lib/update-notifier/updates-available',
            "\n47 updates can be applied immediately.\n12 of these updates are standard security updates.\n",
            $mtime
        );
    }

    public function withMachineId(string $id = self::MACHINE_ID): self
    {
        return $this->write('etc/machine-id', $id . "\n");
    }

    public function withUnreadable(string $relativePath): self
    {
        $this->write($relativePath, '');
        chmod($this->root . '/' . $relativePath, 0000);

        return $this;
    }

    public function path(string $relativePath): string
    {
        return $this->root . '/' . $relativePath;
    }

    public function cleanup(): void
    {
        $this->remove($this->root);
    }

    private function write(string $relativePath, string $contents, ?int $mtime = null): self
    {
        $target = $this->root . '/' . $relativePath;
        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create fixture directory {$dir}");
        }

        file_put_contents($target, $contents);

        if ($mtime !== null) {
            touch($target, $mtime);
        }

        return $this;
    }

    private function remove(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_dir($path)) {
            @chmod($path, 0777);
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
                $this->remove($path . '/' . $entry);
            }
            rmdir($path);

            return;
        }

        @chmod($path, 0666);
        unlink($path);
    }
}
