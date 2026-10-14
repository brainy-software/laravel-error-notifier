<?php

namespace Brainy\ErrorNotifier;

use Throwable;

class IgnoredExceptions
{
    /**
     * @var list<class-string<Throwable>>
     */
    protected array $ignoredClasses;

    /**
     * @param list<class-string<Throwable>> $ignoredClasses
     */
    public function __construct(array $ignoredClasses = [])
    {
        $this->ignoredClasses = $ignoredClasses;
    }

    /**
     * Determine if the exception should be ignored.
     */
    public function shouldIgnore(Throwable $exception): bool
    {
        foreach ($this->ignoredClasses as $ignoredClass) {
            if ($exception instanceof $ignoredClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add an exception class to the ignore list at runtime.
     *
     * @param class-string<Throwable> $class
     */
    public function ignore(string $class): self
    {
        if (!in_array($class, $this->ignoredClasses, true)) {
            $this->ignoredClasses[] = $class;
        }

        return $this;
    }
}
