<?php

declare(strict_types=1);

namespace Msstc4Symfony\HealthCheckBundle\Test\Integration\Functional;

use Elastic\Transport\Transport;
use Elastica\Client;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\ElasticaConnectionChecker;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('elasticsearch')]
final class ElasticsearchReadinessTest extends TestCase
{
    public function testReadinessPassesAgainstALiveCluster(): void
    {
        $url = getenv('ELASTICSEARCH_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('ELASTICSEARCH_URL is not set');
        }

        $config = class_exists(Transport::class)
            ? ['hosts' => [$url]]
            : ['host' => parse_url($url, PHP_URL_HOST), 'port' => parse_url($url, PHP_URL_PORT)];
        $result = new ElasticaConnectionChecker(new Client($config), 'main')->check(new CheckResult(), new Context(CheckTypeEnum::READINESS));

        self::assertMatchesRegularExpression(
            '/^Elastica connection \(main\) passed \(cluster status: (green|yellow)\)$/',
            $result->messages[0] ?? '',
        );
    }
}
