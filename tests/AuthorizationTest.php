<?php

declare(strict_types=1);

namespace astuteo\astuteopulse\tests;

use astuteo\astuteopulse\services\BroadcastStatusService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * checkAuthorized() reads the live request, so the comparison it delegates to is what is
 * covered here. Header and query-parameter transport are verified by requesting the route.
 */
final class AuthorizationTest extends TestCase
{
    private const KEY = 'a-real-looking-site-key';

    #[Test]
    public function a_correct_header_credential_authorizes(): void
    {
        self::assertTrue(BroadcastStatusService::credentialMatches(self::KEY, self::KEY, null));
    }

    #[Test]
    public function a_correct_query_parameter_still_authorizes(): void
    {
        self::assertTrue(BroadcastStatusService::credentialMatches(self::KEY, null, self::KEY));
    }

    #[Test]
    public function the_header_wins_when_the_query_parameter_disagrees(): void
    {
        self::assertTrue(BroadcastStatusService::credentialMatches(self::KEY, self::KEY, 'wrong'));
        self::assertFalse(BroadcastStatusService::credentialMatches(self::KEY, 'wrong', self::KEY));
    }

    #[Test]
    public function a_wrong_credential_in_either_position_is_rejected(): void
    {
        self::assertFalse(BroadcastStatusService::credentialMatches(self::KEY, 'wrong', null));
        self::assertFalse(BroadcastStatusService::credentialMatches(self::KEY, null, 'wrong'));
    }

    #[Test]
    public function a_request_with_no_credential_is_rejected(): void
    {
        self::assertFalse(BroadcastStatusService::credentialMatches(self::KEY, null, null));
        self::assertFalse(BroadcastStatusService::credentialMatches(self::KEY, '', ''));
    }

    #[Test]
    public function an_unset_site_key_rejects_rather_than_authorizing(): void
    {
        // getenv() returns false when the variable is unset, which would fatal in hash_equals.
        self::assertFalse(BroadcastStatusService::credentialMatches(false, 'anything', null));
        self::assertFalse(BroadcastStatusService::credentialMatches('', '', null));
        self::assertFalse(BroadcastStatusService::credentialMatches(null, null, null));
    }
}
