<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Presentation\Controller;

use MaxShamaev\HealthCheckBundle\Application\Health\Check;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\ActionInterface;
use MaxShamaev\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    private const string STATUS_HEADER = 'Status';

    private const string PING_RESPONSE = 'pong';

    private const string FORMAT_JSON = 'json';

    private const string RESULT_UP = 'up';

    private const string RESULT_DOWN = 'down';

    private const string CONTENT_TYPE_PLAIN = 'text/plain';

    public function __construct(
        private readonly ActionInterface $action,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/_/healthcheck/ping', name: 'healthcheck-ping', methods: 'GET')]
    public function ping(): Response
    {
        return new Response(self::PING_RESPONSE, Response::HTTP_OK, [self::STATUS_HEADER => Response::HTTP_OK]);
    }

    #[Route('/_/healthcheck/readiness', name: 'healthcheck-readiness', methods: 'GET')]
    public function readiness(Request $request): Response
    {
        return $this->runHealthCheck(
            $request,
            CheckTypeEnum::READINESS,
            LogLevel::INFO,
            'Application is ready',
            'Application not ready',
        );
    }

    #[Route('/_/healthcheck/liveliness', name: 'healthcheck-liveliness', methods: 'GET')]
    public function liveliness(Request $request): Response
    {
        return $this->runHealthCheck(
            $request,
            CheckTypeEnum::LIVELINESS,
            LogLevel::DEBUG,
            'Application is alive',
            'Application not live',
        );
    }

    private function runHealthCheck(
        Request $request,
        CheckTypeEnum $type,
        string $okLevel,
        string $okMessage,
        string $failMessage,
    ): Response {
        $result = $this->action->run(new Check\DTO\Request($type));

        if ($result->success) {
            $this->logger->log($okLevel, $okMessage, ['messages' => $result->messages]);
        } else {
            $this->logger->warning($failMessage, ['errors' => $result->errors, 'messages' => $result->messages]);
        }

        return $this->formatOutput($request, $result);
    }

    private function formatOutput(Request $request, Check\DTO\Response $result): Response
    {
        $code = $result->success ? Response::HTTP_OK : Response::HTTP_NOT_ACCEPTABLE;

        if ($request->getRequestFormat() === self::FORMAT_JSON) {
            return new JsonResponse($result, $code, [self::STATUS_HEADER => $code]);
        }

        return new Response(
            'Result: ' . ($result->success ? self::RESULT_UP : self::RESULT_DOWN) . PHP_EOL
            . 'Errors: ' . ($result->errors !== [] ? PHP_EOL . implode(PHP_EOL . "\t", $result->errors) : 'none') . PHP_EOL
            . 'Messages: ' . ($result->messages !== [] ? PHP_EOL . implode(PHP_EOL . "\t", $result->messages) : 'none') . PHP_EOL,
            $code,
            [self::STATUS_HEADER => $code, 'Content-Type' => self::CONTENT_TYPE_PLAIN],
        );
    }
}
