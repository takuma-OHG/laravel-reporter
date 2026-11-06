<?php

declare(strict_types=1);

namespace Takuma\LaravelReporter;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReportController
{
    public const VERSION = '1.0.0';

    public function __construct(private readonly Config $config, private readonly Application $app) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Jede Ablehnung sieht aus wie eine fehlende Route, damit der Endpunkt nicht auffindbar ist.
        if ($request->getRealMethod() !== 'GET' || ! $this->tokenMatches($request) || ! $this->ipAllowed($request)) {
            // Gleiche Meldung wie der Router (AbstractRouteCollection), sonst verrät die JSON-404 den Endpunkt.
            throw new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
        }

        return new JsonResponse([
            'schema_version' => 1,
            'generated_at' => date(DATE_ATOM),
            'reporter_version' => self::VERSION,
            'system' => 'laravel',
            'system_version' => $this->app->version(),
            'php_version' => PHP_VERSION,
            'environment' => $this->app->environment(),
            'packages' => $this->packages(),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    private function tokenMatches(Request $request): bool
    {
        $expected = $this->config->get('takuma-reporter.token');
        $given = $request->bearerToken();

        return is_string($expected) && $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }

    private function ipAllowed(Request $request): bool
    {
        /** @var list<string> $allowed */
        $allowed = (array) $this->config->get('takuma-reporter.ips', []);

        return $allowed !== [] && IpUtils::checkIp($this->clientIp($request), $allowed);
    }

    /** Von rechts lesen und vertraute Proxys überspringen: alles links vom ersten fremden Eintrag kann der Client fälschen. */
    private function clientIp(Request $request): string
    {
        // Bewusst REMOTE_ADDR statt ip(): die TrustProxies-Einstellung der App soll hier nicht greifen.
        $ip = $request->server->get('REMOTE_ADDR');
        $ip = is_string($ip) ? $ip : '';
        /** @var list<string> $proxies */
        $proxies = (array) $this->config->get('takuma-reporter.trusted_proxies', []);
        // Alle Header-Zeilen, sonst gewinnt bei mehrfach gesendetem Header die Zeile des Clients.
        $hops = explode(',', implode(',', array_filter($request->headers->all('X-Forwarded-For'), 'is_string')));

        while ($proxies !== [] && $ip !== '' && IpUtils::checkIp($ip, $proxies) && $hops !== []) {
            $ip = trim((string) array_pop($hops));
        }

        return $ip;
    }

    /** @return list<array{name: string, version: string}> */
    private function packages(): array
    {
        $path = $this->app->basePath('composer.lock');
        $lock = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($lock) || ! is_array($lock['packages'] ?? null)) {
            return [];
        }

        $packages = [];
        foreach ($lock['packages'] as $package) {
            if (is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
                $packages[] = ['name' => $package['name'], 'version' => $package['version']];
            }
        }

        return $packages;
    }
}
