<?php

namespace Tests\Unit;

use App\Support\DatabaseConnectionFailureDetector;
use PDOException;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionFailureDetectorTest extends TestCase
{
    public function test_it_detects_access_denied_errors(): void
    {
        $detector = new DatabaseConnectionFailureDetector();
        $exception = new PDOException('SQLSTATE[HY000] [1045] Access denied for user \'feralix\'@\'localhost\'');

        $this->assertTrue($detector->isConnectionFailure($exception));
        $this->assertSame('access_denied', $detector->describe($exception, 'mysql')['reason']);
    }

    public function test_it_detects_missing_driver_errors(): void
    {
        $detector = new DatabaseConnectionFailureDetector();
        $exception = new PDOException('could not find driver');

        $this->assertTrue($detector->isConnectionFailure($exception));
        $this->assertSame('driver_missing', $detector->describe($exception, 'mysql')['reason']);
    }
}
