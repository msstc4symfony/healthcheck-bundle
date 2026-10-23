<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\Run;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\SharedProbe;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Run\SharedProbes;
use PHPUnit\Framework\TestCase;

final class SharedProbesTest extends TestCase
{
    public function testKnowsNothingBeforeAProbeIsRecorded(): void
    {
        $probes = new SharedProbes();

        self::assertNull($probes->outcomeOf('redis:c'));
        self::assertNull($probes->reuse('redis:c'));
        self::assertNull($probes->takeReusedDurationMs());
    }

    public function testTheFirstRecordedOutcomeWins(): void
    {
        $probes = new SharedProbes();
        $first = new SharedProbe('First', null, 5);

        $probes->record('redis:c', $first);
        $probes->record('redis:c', new SharedProbe('Second', 'boom', 9));

        self::assertSame($first, $probes->outcomeOf('redis:c'));
        self::assertNull($probes->outcomeOf('redis:other'));
    }

    public function testReusingAnOutcomeHandsItsDurationToTheTimeoutDecoratorOnce(): void
    {
        $probes = new SharedProbes();
        $probes->record('redis:c', new SharedProbe('First', null, 2500));

        self::assertNotNull($probes->outcomeOf('redis:c'));
        self::assertNull($probes->takeReusedDurationMs(), 'peeking is not reusing');

        $probes->reuse('redis:c');

        self::assertSame(2500, $probes->takeReusedDurationMs());
        self::assertNull($probes->takeReusedDurationMs());
    }
}
