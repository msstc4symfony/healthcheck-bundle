<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Presentation\Command;

use MaxShamaev\HealthCheckBundle\Application\Health\Check\ActionInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\DTO\Request;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractHealthCommand extends Command
{
    private ActionInterface $action;

    abstract protected function getType(): CheckTypeEnum;

    #[Required]
    public function setAction(ActionInterface $action): void
    {
        $this->action = $action;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->action->run(new Request($this->getType()));

        $output->writeln('Result: ' . ($result->success ? '<info>success</info>' : '<error>failed</error>'));

        if ($result->errors !== []) {
            $output->writeln('Errors:');
            foreach ($result->errors as $message) {
                $output->writeln('	<error>' . $message . '</error>');
            }
        }

        if ($result->messages !== []) {
            $verbosity = $result->errors !== [] ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE;
            $output->writeln('Messages:', $verbosity);
            foreach ($result->messages as $message) {
                $output->writeln('	<info>' . $message . '</info>', $verbosity);
            }
        }

        return $result->success ? Command::SUCCESS : Command::FAILURE;
    }
}
