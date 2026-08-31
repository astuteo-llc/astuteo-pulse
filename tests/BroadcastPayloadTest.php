<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\BroadcastStatusService;
use astuteo\astuteopulse\services\HostStatusService;
use astuteo\astuteopulse\tests\support\HostTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * BroadcastStatusService itself needs a booted Craft application, so the payload assembly is
 * verified by requesting the route. What is covered here is the host block's contract with it.
 */
final class BroadcastPayloadTest extends HostTestCase
{
    #[Test]
    public function the_unavailable_shape_matches_the_real_shape_field_for_field(): void
    {
        $fixture = $this->fixture()->withNotifierHelper()->withMachineId();

        self::assertSame(
            array_keys((new HostStatusService($fixture->root()))->toArray()),
            array_keys(HostStatusService::unavailable()),
            'The fallback shape has drifted from the shape a real read returns.'
        );
    }

    #[Test]
    public function the_unavailable_shape_reports_a_reason_for_every_field(): void
    {
        $host = HostStatusService::unavailable();

        foreach (['reboot_pending', 'reboot_pending_since', 'auto_updates_enabled', 'last_check_at', 'host_id'] as $field) {
            self::assertNull($host[$field]);
            self::assertSame('reader-failed', $this->reasons($host['unknown'])[$field]);
        }
    }

    #[Test]
    public function the_unavailable_shape_keeps_the_same_json_type_as_a_real_read(): void
    {
        $fixture = $this->fixture()->withNotifierHelper()->withMachineId();

        $real = json_decode(json_encode((new HostStatusService($fixture->root()))->toArray()), false);
        $fallback = json_decode(json_encode(HostStatusService::unavailable()), false);

        self::assertIsArray($real->unknown);
        self::assertIsArray($fallback->unknown);
    }

    #[Test]
    public function no_reason_the_reader_emits_leaks_a_filesystem_path(): void
    {
        // Assert against reasons the reader actually produces, not a literal written here.
        $fixture = $this->fixture()->withNotifierHelper()->withMachineId('uninitialized');
        $emitted = (new HostStatusService($fixture->root()))->toArray()['unknown'];

        self::assertNotEmpty($emitted);

        $reasons = array_merge(
            array_column($emitted, 'reason'),
            array_column(HostStatusService::unavailable()['unknown'], 'reason'),
            [
                HostStatusService::REASON_NOTIFIER_ABSENT,
                HostStatusService::REASON_MISSING,
                HostStatusService::REASON_UNREADABLE,
                HostStatusService::REASON_UNPARSEABLE,
                HostStatusService::REASON_EMPTY,
                HostStatusService::REASON_INVALID,
                HostStatusService::REASON_READER_FAILED,
            ]
        );

        foreach ($reasons as $reason) {
            self::assertStringNotContainsString('/', $reason, "Reason leaks a path: {$reason}");
        }
    }

    #[Test]
    public function the_credential_header_name_is_the_documented_one(): void
    {
        // Pinned as a literal so a rename cannot silently fall back to the deprecated query param.
        self::assertSame('X-Astuteo-Pulse-Key', BroadcastStatusService::CREDENTIAL_HEADER);
    }
}
