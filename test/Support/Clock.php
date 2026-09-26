<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests\Support;

/** Fake clock: records every sleep the SDK asks for instead of sleeping. */
final class Clock
{
    /** @var list<int> */
    public array $sleeps = [];

    /** @return \Closure(int): void */
    public function sleeper(): \Closure
    {
        return function (int $ms): void {
            $this->sleeps[] = $ms;
        };
    }
}
