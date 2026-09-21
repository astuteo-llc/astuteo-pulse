<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\PluginUpdatesService;
use craft\models\Updates as UpdatesModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PluginUpdatesServiceTest extends TestCase
{
    #[Test]
    public function a_plugin_with_newer_releases_is_reported(): void
    {
        $described = PluginUpdatesService::describe($this->updates([
            'scout' => $this->plugin([
                ['version' => '6.1.2', 'critical' => false],
                ['version' => '6.1.0', 'critical' => false],
            ]),
        ]), true);

        self::assertSame([[
            'handle' => 'scout',
            'latestVersion' => '6.1.2',
            'releases' => 2,
            'critical' => false,
            'status' => 'eligible',
        ]], $described);
    }

    #[Test]
    public function a_plugin_with_no_newer_release_is_omitted_whatever_its_edition(): void
    {
        $described = PluginUpdatesService::describe($this->updates([
            'imager-x' => $this->plugin([]),
        ]), true);

        self::assertSame([], $described);
    }

    #[Test]
    public function any_critical_release_marks_the_update_critical(): void
    {
        $described = PluginUpdatesService::describe($this->updates([
            'scout' => $this->plugin([
                ['version' => '6.1.2', 'critical' => false],
                ['version' => '6.1.0', 'critical' => true],
            ]),
        ]), true);

        self::assertTrue($described[0]['critical']);
    }

    #[Test]
    public function an_expired_licence_status_is_passed_through(): void
    {
        $described = PluginUpdatesService::describe($this->updates([
            'scout' => $this->plugin([['version' => '6.1.2', 'critical' => false]], 'expired'),
        ]), true);

        self::assertSame('expired', $described[0]['status']);
    }

    #[Test]
    public function a_failed_check_reports_null_rather_than_no_updates(): void
    {
        self::assertNull(PluginUpdatesService::describe(new UpdatesModel([]), false));
    }

    #[Test]
    public function a_checked_site_with_nothing_to_update_reports_an_empty_list(): void
    {
        self::assertSame([], PluginUpdatesService::describe($this->updates([]), true));
    }

    #[Test]
    public function the_json_type_is_a_list_whether_or_not_anything_needs_updating(): void
    {
        $none = json_decode(json_encode(PluginUpdatesService::describe($this->updates([]), true)), false);
        $some = json_decode(json_encode(PluginUpdatesService::describe($this->updates([
            'scout' => $this->plugin([['version' => '6.1.2', 'critical' => false]]),
        ]), true)), false);

        self::assertIsArray($none);
        self::assertIsArray($some);
    }

    /**
     * @param array<string, array<string, mixed>> $plugins
     */
    private function updates(array $plugins): UpdatesModel
    {
        return new UpdatesModel(['plugins' => $plugins]);
    }

    /**
     * @param list<array{version: string, critical: bool}> $releases
     * @return array<string, mixed>
     */
    private function plugin(array $releases, string $status = 'eligible'): array
    {
        return ['status' => $status, 'packageName' => 'vendor/plugin', 'releases' => $releases];
    }
}
