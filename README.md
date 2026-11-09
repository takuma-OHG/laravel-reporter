# takuma/laravel-reporter

Stellt `GET /takuma-reporter` bereit. Der takuma manager ruft darüber Laravel-Version, PHP-Version, Umgebung und die Pakete aus `composer.lock` ab (Contract v1). Ungültiger Token, nicht erlaubte IP oder andere Methode als GET: Antwort ist dieselbe 404 wie bei einer unbekannten Route.

Voraussetzung: PHP 8.1+, Laravel 10 bis 13.

## Installation

Das Repo ist öffentlich. Auf dem Server braucht es weder Key noch Token. Installiert wird direkt auf dem Server per SSH, ein Deploy des Projekts ist nicht nötig.

1. Sicherung anlegen:

```bash
cp composer.json composer.json.bak-reporter
cp composer.lock composer.lock.bak-reporter
```

2. In der `composer.json` unter `repositories` das Repo eintragen. Ist `repositories` eine Liste (`[ … ]`):

```json
{ "type": "vcs", "url": "https://github.com/takuma-OHG/laravel-reporter.git", "no-api": true }
```

Ist `repositories` ein Objekt (`{ "local": { … } }`), braucht der Eintrag einen Namen. Sonst meldet Composer ungültiges JSON:

```json
"laravel-reporter": { "type": "vcs", "url": "https://github.com/takuma-OHG/laravel-reporter.git", "no-api": true }
```

Danach prüfen: `composer validate --no-check-publish`.

3. Nur dieses Paket installieren, alle anderen bleiben auf ihrem Stand:

```bash
composer require takuma/laravel-reporter:^1.0 --update-no-dev
php artisan optimize   # nur nötig, wenn Routen oder Config gecacht sind
```

`no-api` lädt per `git clone` statt über die GitHub-API. Ohne Token erlaubt GitHub nur 60 API-Abfragen pro Stunde und IP. Auf Shared Hosting teilen sich viele Kunden eine IP. Auf dem Server muss dafür `git` verfügbar sein.

Bricht Composer mit „is fixed to … (lock file version) by a partial update“ ab, passen `composer.json` und `composer.lock` auf dem Server schon vorher nicht zusammen. Nicht mit `-W` lösen: Dann hebt Composer auf dem Live-System andere Pakete mit an. Entweder die Anforderung in der `composer.json` auf die installierte Version zurücksetzen oder das betroffene Paket bewusst mit aktualisieren (`composer update <paket> takuma/laravel-reporter -w --no-dev --dry-run` zuerst).

Antwortet ein anderes VCS-Repo des Projekts (z. B. auf Bitbucket) mit HTTP 429, ist dessen anonymes API-Limit erreicht. Kurz warten und erneut starten.

Updates: auf dem Server `composer update takuma/laravel-reporter --no-dev`, bei gecachten Routen oder Config danach `php artisan optimize`. Immer eine Versions-Einschränkung wie `^1.0` verwenden, nie `dev-main`. Sonst landet jeder Push auf `main` beim nächsten `composer update` auf dem Kundenserver. Welche Version ein Projekt hat, zeigt der takuma manager im Feld `reporter_version`.

Rückbau: die beiden `.bak-reporter`-Dateien zurückkopieren, dann `composer install --no-dev`, bei gecachten Routen oder Config danach `php artisan optimize`.

Die Änderung an `composer.json` und `composer.lock` entsteht auf dem Server. Damit der Stand im Git des Projekts stimmt, dieselbe Änderung lokal nachziehen.

### Ohne git auf dem Server: Path-Paket

Laufzeitdateien ins Projekt kopieren und als Path-Repository einbinden:

```bash
rsync -a --exclude .git --exclude .github --exclude tests --exclude vendor \
  --exclude composer.lock --exclude 'php*.dist' --exclude .phpunit.result.cache \
  ~/ARBEIT/PLUGINS/LARAVEL/laravel-reporter/ packages/laravel-reporter/
```

Dann `{ "type": "path", "url": "packages/*" }` als Repository eintragen und `composer require takuma/laravel-reporter:@dev`. Updates heißen hier: Kopie erneuern, dann `composer update takuma/laravel-reporter`.

## Konfiguration (.env)

```dotenv
TAKUMA_REPORTER_TOKEN=<64 Zeichen aus dem takuma manager>
TAKUMA_REPORTER_IPS=203.0.113.10,2001:db8::/32
# nur hinter Load Balancer/Proxy: dessen IPs, vertraute Hops werden in X-Forwarded-For übersprungen
TAKUMA_REPORTER_TRUSTED_PROXIES=
```

Hinter einem Proxy (nginx, Docker, Load Balancer) steht in `REMOTE_ADDR` die Proxy-IP. Diese nie in `TAKUMA_REPORTER_IPS` eintragen, sonst besteht jeder Aufrufer die IP-Prüfung. Stattdessen die Proxy-IPs in `TAKUMA_REPORTER_TRUSTED_PROXIES`. Bei mehreren Proxys hintereinander alle eintragen.

Pflicht für jeden vertrauten Proxy: Er hängt die IP seines Gegenübers an `X-Forwarded-For` an (nginx: `proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`). Ein Proxy, der den Header vom Client nur durchreicht, macht die IP-Prüfung wertlos.

CDN-Bereiche (Cloudflare, CloudFront usw.) nicht als `TAKUMA_REPORTER_TRUSTED_PROXIES` eintragen. Über diese Adressen kann jeder andere Kunde des CDN Anfragen schicken und dabei `X-Forwarded-For` frei setzen. Läuft die Seite hinter einem CDN, im takuma manager als Reporter-URL einen Hostnamen eintragen, der direkt auf den Server zeigt (am CDN vorbei). Dann steht die Manager-IP in `REMOTE_ADDR`.

Ohne Token oder ohne erlaubte IPs antwortet der Endpunkt immer 404. Bei gecachter Config danach `php artisan config:cache`.

## Bekannte Grenzen

Die abgelehnte Antwort gleicht der Framework-404. Hat die App eine `Route::fallback` oder eine Catch-all-Route (z. B. CMS-Slugs), sieht die 404 des Reporters anders aus als die Seite für unbekannte Pfade. Mit `APP_DEBUG=true` zeigt die Fehlerseite den Controller.

## Entwicklung

```bash
composer test   # Pint, PHPStan Level 9, PHPUnit
```
