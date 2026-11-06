# takuma/laravel-reporter

Stellt `GET /takuma-reporter` bereit. Der takuma manager ruft darüber Laravel-Version, PHP-Version, Umgebung und die Pakete aus `composer.lock` ab (Contract v1). Ungültiger Token, nicht erlaubte IP oder andere Methode als GET: Antwort ist dieselbe 404 wie bei einer unbekannten Route.

Voraussetzung: PHP 8.1+, Laravel 10 bis 13.

## Installation

`composer.json` des Projekts:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/takuma-OHG/laravel-reporter" }
]
```

`auth.json` (nicht committen) mit einem read-only GitHub-Token:

```json
{ "github-oauth": { "github.com": "<token>" } }
```

```bash
composer require takuma/laravel-reporter
```

## Konfiguration (.env)

```dotenv
TAKUMA_REPORTER_TOKEN=<64 Zeichen aus dem takuma manager>
TAKUMA_REPORTER_IPS=203.0.113.10,2001:db8::/32
# nur hinter Load Balancer/Proxy: dessen IPs, vertraute Hops werden in X-Forwarded-For übersprungen
TAKUMA_REPORTER_TRUSTED_PROXIES=
```

Hinter einem Proxy (nginx, Docker, Cloudflare, Load Balancer) steht in `REMOTE_ADDR` die Proxy-IP. Diese nie in `TAKUMA_REPORTER_IPS` eintragen, sonst besteht jeder Aufrufer die IP-Prüfung. Stattdessen die Proxy-IPs in `TAKUMA_REPORTER_TRUSTED_PROXIES`. Bei mehreren Proxys hintereinander alle eintragen.

Ohne Token oder ohne erlaubte IPs antwortet der Endpunkt immer 404. Bei gecachter Config danach `php artisan config:cache`.

## Bekannte Grenzen

Die abgelehnte Antwort gleicht der Framework-404. Hat die App eine `Route::fallback` oder eine Catch-all-Route (z. B. CMS-Slugs), sieht die 404 des Reporters anders aus als die Seite für unbekannte Pfade. Mit `APP_DEBUG=true` zeigt die Fehlerseite den Controller.

## Entwicklung

```bash
composer test   # Pint, PHPStan Level 9, PHPUnit
```
