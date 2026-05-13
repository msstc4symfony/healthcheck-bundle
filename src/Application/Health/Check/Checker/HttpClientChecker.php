<?php

declare(strict_types=1);

namespace MaxShamaev\HealthCheckBundle\Application\Health\Check\Checker;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Exclude]
final readonly class HttpClientChecker extends AbstractReadinessChecker
{
    /**
     * @param list<int> $expectedStatusCodes
     */
    public function __construct(
        private HttpClientInterface $client,
        private string $name,
        private string $url,
        private string $method,
        private array $expectedStatusCodes,
        private int $timeoutSeconds,
    ) {
    }

    protected function doCheck(): void
    {
        $response = $this->client->request($this->method, $this->url, [
            'timeout' => $this->timeoutSeconds,
        ]);
        $status = $response->getStatusCode();

        if (!in_array($status, $this->expectedStatusCodes, true)) {
            throw new RuntimeException(sprintf('unexpected status %d', $status));
        }
    }

    protected function label(): string
    {
        return sprintf('HTTP probe (%s)', $this->name);
    }
}
