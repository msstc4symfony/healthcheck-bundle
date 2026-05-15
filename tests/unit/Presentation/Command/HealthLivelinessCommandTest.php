<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Test\Unit\Presentation\Command;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Action;
use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use MSSTC4PHP\HealthCheckBundle\Presentation\Command\HealthLivelinessCommand;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\FailChecker;
use MSSTC4PHP\HealthCheckBundle\Test\Mock\Application\Health\Check\Checker\SuccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class HealthLivelinessCommandTest extends TestCase
{
    /**
     * @param CheckInterface[] $checkers
     */
    #[DataProvider('getDataForTestExecute')]
    public function testExecute(array $checkers, string $expect): void
    {
        $command = new HealthLivelinessCommand(new Action($checkers));

        $input = new ArrayInput([], $command->getDefinition());
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $command->run($input, $output);

        self::assertSame($expect, $output->fetch());
    }

    /**
     * @return array<string, array{checkers: CheckInterface[], expect: string}>
     */
    public static function getDataForTestExecute(): array
    {
        return [
            'success empty' => [
                'checkers' => [],
                'expect' => "Result: success\n",
            ],
            'success' => [
                'checkers' => [
                    new SuccessChecker(),
                ],
                'expect' => "Result: success\nMessages:\n\tsuccess dump check\n",
            ],
            'failed' => [
                'checkers' => [
                    new FailChecker(),
                ],
                'expect' => "Result: failed\nErrors:\n\tfail dump check\n",
            ],
        ];
    }
}
