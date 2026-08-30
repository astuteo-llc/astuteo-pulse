<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\HostStatusService;
use astuteo\astuteopulse\tests\support\HostFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HostIdentityTest extends TestCase
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
    public function sites_on_the_same_host_derive_the_same_identifier(): void
    {
        $fixture = $this->fixture()->withMachineId();

        $first = (new HostStatusService($fixture->root()))->toArray();
        $second = (new HostStatusService($fixture->root()))->toArray();

        self::assertNotNull($first['host_id']);
        self::assertSame($first['host_id'], $second['host_id']);
    }

    #[Test]
    public function different_hosts_derive_different_identifiers(): void
    {
        $one = $this->fixture()->withMachineId('11111111111111111111111111111111');
        $two = $this->fixture()->withMachineId('22222222222222222222222222222222');

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
        self::assertSame('source-missing', $host['unknown']['host_id']);
    }

    #[Test]
    public function an_empty_machine_id_reports_unknown_rather_than_hashing_nothing(): void
    {
        $fixture = $this->fixture()->withMachineId('');

        $host = (new HostStatusService($fixture->root()))->toArray();

        self::assertNull($host['host_id']);
        self::assertSame('machine-id-empty', $host['unknown']['host_id']);
    }

    private function fixture(): HostFixture
    {
        $fixture = HostFixture::create();
        $this->fixtures[] = $fixture;

        return $fixture;
    }
}
