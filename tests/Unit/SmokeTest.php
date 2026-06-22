<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lightweight test that does not bootstrap Laravel and does not touch the database.
 */
class SmokeTest extends TestCase
{
    public function test_suite_is_configured(): void
    {
        $this->assertTrue(true);
    }
}
