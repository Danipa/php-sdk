<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Http\HttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * #7744: the transport the SDK picks for itself (no client injected) must not
 * follow redirects, or the X-API-Key header would be resent to the redirect
 * target. Two `php -S` servers on different loopback ports are two origins:
 * the first answers every request with a redirect to the second, and the
 * second records what it receives. Nothing leaves the machine.
 */
final class DefaultTransportTest extends TestCase
{
    private const API_KEY = 'dk_test_topsecret';

    private static string $dir;
    private static string $apiOrigin;
    /** @var list<resource> */
    private static array $servers = [];

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/danipa-sdk-redirect-' . bin2hex(random_bytes(4));
        mkdir(self::$dir);
        $otherPort = self::freePort();
        file_put_contents(self::$dir . '/other.php', <<<'PHP'
            <?php
            file_put_contents(__DIR__ . '/seen.log', json_encode([
                'uri' => $_SERVER['REQUEST_URI'],
                'apiKey' => $_SERVER['HTTP_X_API_KEY'] ?? null,
            ]) . "\n", FILE_APPEND);
            header('Content-Type: application/json');
            echo '{}';
            PHP);
        file_put_contents(self::$dir . '/api.php', sprintf(<<<'PHP'
            <?php
            http_response_code(302);
            header('Location: http://127.0.0.1:%d/different-origin');
            PHP, $otherPort));
        self::start($otherPort, 'other.php');
        $apiPort = self::freePort();
        self::start($apiPort, 'api.php');
        self::$apiOrigin = 'http://127.0.0.1:' . $apiPort;
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$servers as $process) {
            proc_terminate($process);
            proc_close($process);
        }
        self::$servers = [];
        array_map('unlink', glob(self::$dir . '/*') ?: []);
        @rmdir(self::$dir);
    }

    protected function setUp(): void
    {
        @unlink(self::$dir . '/seen.log');
    }

    public function testTheTransportPickedWithGuzzleAndSymfonyInstalledDoesNotFollowRedirects(): void
    {
        // Both clients are installed here (require-dev), the case where
        // php-http/discovery on its own picks Symfony's redirect-following client.
        $this->assertRedirectIsNotFollowed(new HttpClient(
            apiKey: self::API_KEY,
            baseUrl: self::$apiOrigin . '/ms',
            maxRetries: 0,
        ));
    }

    public function testTheTransportPickedWithOnlySymfonyInstalledDoesNotFollowRedirects(): void
    {
        $symfonyOnly = static fn (string $class): bool => !str_starts_with($class, 'GuzzleHttp\\');
        $transport = HttpClient::defaultTransport($symfonyOnly);
        self::assertInstanceOf(Psr18Client::class, $transport);

        $this->assertRedirectIsNotFollowed(new HttpClient(
            apiKey: self::API_KEY,
            baseUrl: self::$apiOrigin . '/ms',
            maxRetries: 0,
            client: $transport,
        ));
    }

    public function testGuzzleIsPreferredWhenInstalled(): void
    {
        self::assertInstanceOf(\GuzzleHttp\Client::class, HttpClient::defaultTransport());
    }

    public function testOtherClientsFallBackToDiscovery(): void
    {
        $neither = static fn (string $class): bool =>
            !str_starts_with($class, 'GuzzleHttp\\') && !str_starts_with($class, 'Symfony\\');
        self::assertSame(
            \Http\Discovery\Psr18ClientDiscovery::find()::class,
            HttpClient::defaultTransport($neither)::class,
        );
    }

    private function assertRedirectIsNotFollowed(HttpClient $http): void
    {
        $error = null;
        try {
            $http->request('GET', '/v1/merchants/me');
        } catch (DanipaApiError $e) {
            $error = $e;
        }

        $seen = @file_get_contents(self::$dir . '/seen.log');
        self::assertSame('', $seen === false ? '' : $seen, 'the redirect target must receive nothing');
        self::assertNotNull($error, 'a redirect surfaces as an API error');
        self::assertSame(302, $error->status);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function start(int $port, string $router): void
    {
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, self::$dir . '/' . $router],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        self::$servers[] = $process;
        for ($i = 0; $i < 100; $i++) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(50_000);
        }
        self::fail('local test server did not start on port ' . $port);
    }
}
