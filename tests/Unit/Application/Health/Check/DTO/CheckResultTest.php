<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Unit\Application\Health\Check\DTO;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckResult::class)]
final class CheckResultTest extends TestCase
{
    public function testCollectsMessagesErrorsAndWarningsInOrder(): void
    {
        $result = new CheckResult();
        $result->addMessage('m1');
        $result->addError('e1');
        $result->addWarning('w1');
        $result->addMessage('m2');

        self::assertSame(['m1', 'm2'], $result->messages);
        self::assertSame(['e1'], $result->errors);
        self::assertSame(['w1'], $result->warnings);
    }

    public function testResetTrailingKeepsOnlyTheRecordedPrefixOfEachList(): void
    {
        $result = new CheckResult();
        foreach (['m1', 'm2', 'm3'] as $message) {
            $result->addMessage($message);
        }

        $result->addError('e1');
        $result->addWarning('w1');
        $result->addWarning('w2');

        $result->resetTrailing(1, 0, 1);

        self::assertSame(['m1'], $result->messages);
        self::assertSame([], $result->errors);
        self::assertSame(['w1'], $result->warnings);
    }
}
