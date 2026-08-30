<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\HostStatusService;
use astuteo\astuteopulse\tests\support\HostFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * BroadcastStatusService itself needs a booted Craft application, so the payload assembly is
 * verified by requesting the route. What is covered here is the host block's contract with it.
 */
final class BroadcastPayloadTest extends TestCase
{
    #[Test]
    public function the_unavailable_shape_matches_the_real_shape_field_for_field(): void
    {
        $fixture = HostFixture::create()->withNotifierHelper()->withMachineId();

        try {
            $real = (new HostStatusService($fixture->root()))->toArray();
        } finally {
            $fixture->cleanup();
        }

        self::assertSame(
            array_keys($real),
            array_keys(HostStatusService::unavailable('reader-failed')),
            'The fallback shape has drifted from the shape a real read returns.'
        );
    }

    #[Test]
    public function the_unavailable_shape_reports_a_reason_for_every_field(): void
    {
        $host = HostStatusService::unavailable('reader-failed');

        foreach (['reboot_pending', 'reboot_pending_since', 'auto_updates_enabled', 'last_check_at', 'host_id'] as $field) {
            self::assertNull($host[$field]);
            self::assertSame('reader-failed', $host['unknown'][$field]);
        }
    }

    #[Test]
    public function the_unavailable_shape_emits_no_disclosing_value(): void
    {
        $encoded = json_encode(HostStatusService::unavailable('reader-failed'));

        self::assertStringNotContainsString('/', $encoded, 'A reason must not leak a filesystem path.');
    }
}
