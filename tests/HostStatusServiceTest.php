<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\HostStatusService;
use astuteo\astuteopulse\tests\support\HostFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HostStatusServiceTest extends TestCase
{
    private array $fixtures = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $fixture) {
            $fixture->cleanup();
        }

        $this->fixtures = [];
    }

    #[Test]
    public function missing_notifier_helper_reports_reboot_pending_as_unknown_not_false(): void
    {
        $fixture = $this->fixture()
            ->withAutoUpgrades(true)
            ->withUpdateSuccessStamp(time() - 3600)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertNull(
            $host['reboot_pending'],
            'A host that cannot raise the reboot flag must not report as not-needing-a-reboot.'
        );
        self::assertSame('reboot-notifier-absent', $host['unknown']['reboot_pending']);
    }

    #[Test]
    public function reboot_pending_reports_the_flag_and_when_it_appeared(): void
    {
        $staged = time() - (4 * 86400);

        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withRebootRequired($staged)
            ->withRebootRequiredPkgs("linux-image-5.4.0-91-generic\n")
            ->withAutoUpgrades(true)
            ->withUpdateSuccessStamp(time() - 3600)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertTrue($host['reboot_pending']);
        self::assertSame(gmdate('c', $staged), $host['reboot_pending_since']);
        self::assertSame([], $host['unknown']);
        self::assertStringNotContainsString('linux-image', json_encode($host));
    }

    #[Test]
    public function a_healthy_host_reports_no_reboot_and_live_updates(): void
    {
        $checked = time() - 3600;

        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withAutoUpgrades(true)
            ->withUpdateSuccessStamp($checked)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertFalse($host['reboot_pending']);
        self::assertNull($host['reboot_pending_since']);
        self::assertTrue($host['auto_updates_enabled']);
        self::assertSame(gmdate('c', $checked), $host['last_check_at']);
        self::assertSame([], $host['unknown']);
    }

    #[Test]
    public function a_host_that_stopped_patching_is_distinguishable_from_a_healthy_one(): void
    {
        $stale = time() - (200 * 86400);

        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withAutoUpgrades(false)
            ->withUpdateSuccessStamp($stale)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertFalse($host['reboot_pending']);
        self::assertFalse($host['auto_updates_enabled']);
        self::assertSame(gmdate('c', $stale), $host['last_check_at']);
    }

    #[Test]
    public function a_bare_root_reports_every_field_unknown_with_a_reason(): void
    {
        $host = (new HostStatusService($this->fixture()->root()))->toArray();

        foreach (['reboot_pending', 'reboot_pending_since', 'auto_updates_enabled', 'last_check_at', 'host_id'] as $field) {
            self::assertNull($host[$field], "{$field} should be unknown on a bare root");
            self::assertArrayHasKey($field, $host['unknown'], "{$field} should carry a reason");
        }
    }

    #[Test]
    public function freshness_falls_back_to_the_notifier_file_when_the_apt_stamp_is_absent(): void
    {
        $checked = time() - 7200;

        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withAutoUpgrades(true)
            ->withUpdatesAvailable($checked)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertSame(gmdate('c', $checked), $host['last_check_at']);
        self::assertArrayNotHasKey('last_check_at', $host['unknown']);
    }

    #[Test]
    public function the_notifier_file_contents_never_reach_the_payload(): void
    {
        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withAutoUpgrades(true)
            ->withUpdatesAvailable(time() - 60)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();
        $encoded = json_encode($host);

        self::assertStringNotContainsString('updates can be applied', $encoded);
        self::assertStringNotContainsString('security updates', $encoded);

        // The file is stat-only, so its freshness must arrive as a timestamp and nothing else.
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $host['last_check_at']);
    }

    #[Test]
    public function unparseable_auto_upgrades_config_reports_unknown_rather_than_guessing(): void
    {
        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withRawAutoUpgrades("// managed elsewhere\n")
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertNull($host['auto_updates_enabled']);
        self::assertSame('config-unparseable', $host['unknown']['auto_updates_enabled']);
    }

    #[Test]
    public function an_unreadable_source_reports_unknown_without_raising(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Permission bits do not restrict root.');
        }

        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withAutoUpgrades(true)
            ->withMachineId()
            ->withUnreadable('etc/apt/apt.conf.d/20auto-upgrades');

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertNull($host['auto_updates_enabled']);
        self::assertSame('source-unreadable', $host['unknown']['auto_updates_enabled']);
    }

    #[Test]
    public function every_emitted_value_is_a_disclosure_safe_type(): void
    {
        $fixture = $this->fixture()
            ->withNotifierHelper()
            ->withRebootRequired(time() - 86400)
            ->withAutoUpgrades(true)
            ->withUpdateSuccessStamp(time() - 3600)
            ->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();
        unset($host['unknown']);

        foreach ($host as $field => $value) {
            self::assertTrue(
                $value === null || is_bool($value) || $this->isTimestamp($value) || $this->isOpaqueId($value),
                "{$field} emitted a value outside the allowed type set"
            );
        }
    }

    private function isTimestamp(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $value) === 1;
    }

    private function isOpaqueId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{64}$/', $value) === 1;
    }

    private function fixture(): HostFixture
    {
        $fixture = HostFixture::create();
        $this->fixtures[] = $fixture;

        return $fixture;
    }
}
