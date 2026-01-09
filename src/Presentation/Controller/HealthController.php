<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Presentation\Controller;

use MaxShamaev\HealthCheckBundle\Application\Health\Check;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ActionInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly ActionInterface $action,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/_/healthcheck/ping', name: 'healthcheck-ping', methods: 'GET')]
    public function ping(): Response
    {
        return new Response('pong', Response::HTTP_OK, ['Status' => Response::HTTP_OK]);
    }

    #[Route('/_/healthcheck/readiness', name: 'healthcheck-readiness', methods: 'GET')]
    public function readiness(Request $request): Response
    {
        $result = $this->action->run(new Check\DTO\Request(CheckTypeEnum::READINESS));
        if ($result->success) {
            $this->logger->info('Application is ready', ['messages' => $result->messages]);
        } else {
            $this->logger->warning('Application not ready', ['errors' => $result->errors, 'messages' => $result->messages]);
        }

        return $this->formatOutput($request, $result);
    }

    #[Route('/_/healthcheck/liveliness', name: 'healthcheck-liveliness', methods: 'GET')]
    public function liveliness(Request $request): Response
    {
        $result = $this->action->run(new Check\DTO\Request(CheckTypeEnum::LIVELINESS));
        if ($result->success) {
            $this->logger->debug('Application is alive', ['messages' => $result->messages]);
        } else {
            $this->logger->warning('Application not live', ['errors' => $result->errors, 'messages' => $result->messages]);
        }

        return $this->formatOutput($request, $result);
    }

    private function formatOutput(Request $request, Check\DTO\Response $result): Response
    {
        $code = $result->success ? Response::HTTP_OK : Response::HTTP_NOT_ACCEPTABLE;

        if ($request->getRequestFormat() === 'json') {
            return new JsonResponse($result, $code, ['Status' => $code]);
        }

        return new Response(
            'Result: ' . ($result->success ? 'up' : 'down') . PHP_EOL
            . 'Errors: ' . ($result->errors !== [] ? PHP_EOL . implode(PHP_EOL . "\t", $result->errors) : 'none') . PHP_EOL
            . 'Messages: ' . ($result->messages !== [] ? PHP_EOL . implode(PHP_EOL . "\t", $result->messages) : 'none') . PHP_EOL,
            $code,
            ['Status' => $code, 'Content-Type' => 'text/plain'],
        );
    }
}
