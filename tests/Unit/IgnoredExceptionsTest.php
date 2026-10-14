<?php

namespace Brainy\ErrorNotifier\Tests\Unit;

use Brainy\ErrorNotifier\IgnoredExceptions;
use Brainy\ErrorNotifier\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IgnoredExceptionsTest extends TestCase
{
    public function test_it_correctly_identifies_ignored_exceptions(): void
    {
        $checker = new IgnoredExceptions([
            NotFoundHttpException::class,
            ValidationException::class,
        ]);

        $notFound = new NotFoundHttpException('Not found');
        $this->assertTrue($checker->shouldIgnore($notFound));

        $serverError = new RuntimeException('Server crashed');
        $this->assertFalse($checker->shouldIgnore($serverError));

        // Runtime dynamic addition
        $checker->ignore(InvalidArgumentException::class);
        $this->assertTrue($checker->shouldIgnore(new InvalidArgumentException('Invalid argument')));
    }
}
