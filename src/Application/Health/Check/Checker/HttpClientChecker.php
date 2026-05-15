<?php

declare(strict_types=1);

namespace MSSTC4PHP\HealthCheckBundle\Application\Health\Check\Checker;

use MSSTC4PHP\HealthCheckBundle\Application\Health\Check\DTO\HttpProbeTarget;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

#[Exclude]
final readonly class HttpClientChecker extends AbstractReadinessChecker
{
    public function __construct(
        private HttpClientInterface $client,
        private string $name,
        private HttpProbeTarget $target,
    ) {
    }

    protected function doCheck(): void
    {
        // The Symfony HttpClient throws TransportException whose message often contains the full
        // URL (including any query string secrets). Wrap to keep the probe response sanitized.
        $response = null;
        try {
            try {
                $response = $this->client->request($this->target->method, $this->target->url, [
                    'timeout' => $this->target->timeoutSeconds,
                ]);
                $status = $response->getStatusCode();
            } catch (Throwable) {
                throw new RuntimeException('HTTP probe transport error');
            }

            if (!in_array($status, $this->target->expectedStatusCodes, true)) {
                throw new RuntimeException(sprintf('unexpected status %d', $status));
            }
        } finally {
            // cancel() can itself raise on some transports if internal state is broken — keep
            // it best-effort so it never replaces the original (sanitized) exception.
            if ($response instanceof ResponseInterface) {
                try {
                    $response->cancel();
                } catch (Throwable) {
                    // ignored
                }
            }
        }
    }

    protected function label(): string
    {
        return sprintf('HTTP probe (%s)', $this->name);
    }
}
