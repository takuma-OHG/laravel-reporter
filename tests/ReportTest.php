<?php

declare(strict_types=1);

namespace Takuma\LaravelReporter\Tests;

use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Takuma\LaravelReporter\ReporterServiceProvider;

final class ReportTest extends TestCase
{
    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private string $lockPath;

    protected function getPackageProviders($app): array
    {
        return [ReporterServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.debug', false);
        $app['config']->set('takuma-reporter.token', self::TOKEN);
        $app['config']->set('takuma-reporter.ips', ['127.0.0.1']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockPath = $this->app->basePath('composer.lock');
        file_put_contents($this->lockPath, json_encode([
            'packages' => [['name' => 'laravel/framework', 'version' => 'v12.0.0', 'type' => 'library']],
            'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '11.0.0']],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);

        parent::tearDown();
    }

    public function test_valid_token_and_ip_returns_contract_v1_with_lock_packages(): void
    {
        $response = $this->report();

        $response->assertOk()
            ->assertJsonStructure(['schema_version', 'generated_at', 'reporter_version', 'system', 'system_version', 'php_version', 'environment', 'packages'])
            ->assertJson([
                'schema_version' => 1,
                'system' => 'laravel',
                'system_version' => $this->app->version(),
                'php_version' => PHP_VERSION,
                'environment' => 'testing',
            ])
            ->assertExactJson([...$response->json(), 'packages' => [['name' => 'laravel/framework', 'version' => 'v12.0.0']]]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    public function test_missing_lock_file_reports_empty_packages(): void
    {
        unlink($this->lockPath);

        $this->report()->assertOk()->assertJsonPath('packages', []);
    }

    /** @return array<string, array{0: callable(self): TestResponse}> */
    public static function rejectedRequests(): array
    {
        return [
            'wrong token' => [fn (self $t) => $t->report(token: str_repeat('b', 64))],
            'missing token' => [fn (self $t) => $t->getJson('/takuma-reporter')],
            'wrong ip' => [fn (self $t) => $t->report(ip: '203.0.113.9')],
            'post' => [fn (self $t) => $t->postJson('/takuma-reporter', [], ['Authorization' => 'Bearer '.self::TOKEN])],
            'empty configured token' => [function (self $t) {
                config(['takuma-reporter.token' => '']);

                return $t->report(token: '');
            }],
            'no allowed ips configured' => [function (self $t) {
                config(['takuma-reporter.ips' => []]);

                return $t->report();
            }],
        ];
    }

    /** @param callable(self): TestResponse $request */
    #[DataProvider('rejectedRequests')]
    public function test_rejected_request_is_indistinguishable_from_framework_404(callable $request): void
    {
        $expected = $this->getJson('/does-not-exist');
        $actual = $request($this);

        $actual->assertNotFound();
        $this->assertSame(str_replace('does-not-exist', 'takuma-reporter', (string) $expected->getContent()), $actual->getContent());
        $this->assertSame($expected->headers->get('Content-Type'), $actual->headers->get('Content-Type'));
    }

    public function test_rejected_html_request_matches_framework_404_page(): void
    {
        $expected = $this->get('/does-not-exist');
        $actual = $this->get('/takuma-reporter', ['Authorization' => 'Bearer '.str_repeat('b', 64)]);

        $actual->assertNotFound();
        $this->assertSame(str_replace('does-not-exist', 'takuma-reporter', (string) $expected->getContent()), $actual->getContent());
    }

    public function test_ipv6_cidr_is_allowed(): void
    {
        config(['takuma-reporter.ips' => ['2001:db8::/32']]);

        $this->report(ip: '2001:db8::1')->assertOk();
    }

    public function test_forwarded_ip_is_used_only_behind_trusted_proxy(): void
    {
        config(['takuma-reporter.ips' => ['198.51.100.7'], 'takuma-reporter.trusted_proxies' => ['10.0.0.1']]);

        $this->report(ip: '10.0.0.1', forwardedFor: '1.2.3.4, 198.51.100.7')->assertOk();
        // Client schreibt die erlaubte IP selbst nach vorn, der Proxy hängt die echte an.
        $this->report(ip: '10.0.0.1', forwardedFor: '198.51.100.7, 1.2.3.4')->assertNotFound();
        // Ohne vertrauten Proxy zählt X-Forwarded-For nicht.
        $this->report(ip: '10.0.0.2', forwardedFor: '198.51.100.7')->assertNotFound();
    }

    public function report(string $token = self::TOKEN, string $ip = '127.0.0.1', ?string $forwardedFor = null): TestResponse
    {
        $headers = ['Authorization' => 'Bearer '.$token];
        if ($forwardedFor !== null) {
            $headers['X-Forwarded-For'] = $forwardedFor;
        }

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson('/takuma-reporter', $headers);
    }
}
