<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests\support;

use PHPUnit\Framework\TestCase;

abstract class HostTestCase extends TestCase
{
    /** @var list<HostFixture> */
    private array $fixtures = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $fixture) {
            $fixture->cleanup();
        }

        $this->fixtures = [];
    }

    protected function fixture(): HostFixture
    {
        $fixture = HostFixture::create();
        $this->fixtures[] = $fixture;

        return $fixture;
    }

    protected function skipIfRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Permission bits do not restrict root.');
        }
    }

    /**
     * @param list<array{field: string, reason: string}> $unknown
     * @return array<string, string>
     */
    protected function reasons(array $unknown): array
    {
        return array_column($unknown, 'reason', 'field');
    }
}
