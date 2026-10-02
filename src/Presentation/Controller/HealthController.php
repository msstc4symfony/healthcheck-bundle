<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Presentation\Controller;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
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

    private const string FORMAT_PARAMETER = '_format';

    private const string RESULT_UP = 'up';

    private const string RESULT_DOWN = 'down';

    private const string CONTENT_TYPE_PLAIN = 'text/plain';

    private const array NEGOTIATED_HEADERS = ['Vary' => 'Accept'];

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

        if ($this->responseFormat($request) === self::FORMAT_JSON) {
            return new JsonResponse($result, $code, [self::STATUS_HEADER => $code, ...self::NEGOTIATED_HEADERS]);
        }

        return new Response(
            'Result: ' . ($result->success ? self::RESULT_UP : self::RESULT_DOWN) . PHP_EOL
            . $this->textSection('Errors', $result->errors)
            . $this->textSection('Messages', $result->messages)
            . $this->textSection('Warnings', $result->warnings),
            $code,
            [self::STATUS_HEADER => $code, 'Content-Type' => self::CONTENT_TYPE_PLAIN, ...self::NEGOTIATED_HEADERS],
        );
    }

    /**
     * @param array<string> $lines
     */
    private function textSection(string $title, array $lines): string
    {
        return $title . ': ' . ($lines !== [] ? PHP_EOL . implode(PHP_EOL . "\t", $lines) : 'none') . PHP_EOL;
    }

    /**
     * A route-level format wins, then the "_format" query parameter, then the Accept header.
     */
    private function responseFormat(Request $request): ?string
    {
        $queryFormat = $request->query->all()[self::FORMAT_PARAMETER] ?? null;

        return $request->getRequestFormat(null)
            ?? (is_string($queryFormat) ? $queryFormat : null)
            ?? $request->getPreferredFormat(null);
    }
}
