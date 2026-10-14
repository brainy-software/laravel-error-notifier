<?php

namespace Brainy\ErrorNotifier\Tests\Unit;

use Brainy\ErrorNotifier\RateLimiter;
use Brainy\ErrorNotifier\Tests\TestCase;
use Exception;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RateLimiterTest extends TestCase
{
    public function test_it_allows_first_occurrence_and_throttles_subsequent_identical_exceptions(): void
    {
        $limiter = new RateLimiter(Cache::store('array'), true, 600);
        $exception = new RuntimeException('Database connection failed', 500);

        // 1st time: Allowed
        $this->assertTrue($limiter->shouldNotify($exception));

        // 2nd time: Throttled
        $this->assertFalse($limiter->shouldNotify($exception));

        // 3rd time: Throttled
        $this->assertFalse($limiter->shouldNotify($exception));

        // Suppressed count should be 2
        $this->assertEquals(2, $limiter->getSuppressedCount($exception));
    }

    public function test_it_allows_different_exceptions(): void
    {
        $limiter = new RateLimiter(Cache::store('array'), true, 600);
        $ex1 = new RuntimeException('Error A');
        $ex2 = new Exception('Error B');

        $this->assertTrue($limiter->shouldNotify($ex1));
        $this->assertTrue($limiter->shouldNotify($ex2));
    }

    public function test_it_does_not_throttle_when_disabled(): void
    {
        $limiter = new RateLimiter(Cache::store('array'), false, 600);
        $exception = new RuntimeException('Some error');

        $this->assertTrue($limiter->shouldNotify($exception));
        $this->assertTrue($limiter->shouldNotify($exception));
    }
}
