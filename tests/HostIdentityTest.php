<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\HostStatusService;
use astuteo\astuteopulse\tests\support\HostFixture;
use astuteo\astuteopulse\tests\support\HostTestCase;
use PHPUnit\Framework\Attributes\Test;

final class HostIdentityTest extends HostTestCase
{
    #[Test]
    public function sites_on_the_same_host_derive_the_same_identifier(): void
    {
        $fixture = $this->fixture()->withMachineId();

        $first = (new HostStatusService($fixture->root()))->toArray();
        $second = (new HostStatusService($fixture->root()))->toArray();

        self::assertNotNull($first['host_id']);
        self::assertSame($first['host_id'], $second['host_id']);
    }

    #[Test]
    public function the_derivation_is_pinned_to_its_key(): void
    {
        $fixture = $this->fixture()->withMachineId();

        // Inlined rather than read from the class, so rotating or dropping the key fails here.
        self::assertSame(
            hash_hmac('sha256', HostFixture::MACHINE_ID, 'astuteo-pulse.host-id.v1'),
            (new HostStatusService($fixture->root()))->toArray()['host_id']
        );
    }

    #[Test]
    public function different_hosts_derive_different_identifiers(): void
    {
        $one = $this->fixture()->withMachineId('1111111111111111111111111111111a');
        $two = $this->fixture()->withMachineId('2222222222222222222222222222222b');

        self::assertNotSame(
            (new HostStatusService($one->root()))->toArray()['host_id'],
            (new HostStatusService($two->root()))->toArray()['host_id']
        );
    }

    #[Test]
    public function the_identifier_never_echoes_the_machine_id(): void
    {
        $fixture = $this->fixture()->withMachineId();

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertStringNotContainsString(HostFixture::MACHINE_ID, $host['host_id']);
        self::assertStringNotContainsString(HostFixture::MACHINE_ID, json_encode($host));
    }

    #[Test]
    public function an_absent_machine_id_reports_unknown(): void
    {
        $host = (new HostStatusService($this->fixture()->root()))->toArray();

        self::assertNull($host['host_id']);
        self::assertSame('source-missing', $this->reasons($host['unknown'])['host_id']);
    }

    #[Test]
    public function an_unreadable_machine_id_reports_unknown(): void
    {
        $this->skipIfRoot();

        $fixture = $this->fixture()->withMachineId()->withUnreadable('etc/machine-id');

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertNull($host['host_id']);
        self::assertSame('source-unreadable', $this->reasons($host['unknown'])['host_id']);
    }

    #[Test]
    public function an_empty_machine_id_reports_unknown_rather_than_hashing_nothing(): void
    {
        $fixture = $this->fixture()->withMachineId('');

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertNull($host['host_id']);
        self::assertSame('machine-id-empty', $this->reasons($host['unknown'])['host_id']);
    }

    #[Test]
    public function a_malformed_machine_id_reports_unknown_rather_than_collapsing_hosts(): void
    {
        // systemd writes this sentinel on a host whose ID was never committed. Hashing it would
        // give every such host in the fleet the same identifier.
        foreach (['uninitialized', 'not-hex-at-all', 'abc123'] as $value) {
            $fixture = $this->fixture()->withMachineId($value);

            $host = (new HostStatusService($fixture->root()))->toArray();

            self::assertNull($host['host_id'], "Malformed machine-id must not be hashed: {$value}");
            self::assertSame('machine-id-invalid', $this->reasons($host['unknown'])['host_id']);
        }
    }
}
