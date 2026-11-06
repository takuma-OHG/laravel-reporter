# takuma/laravel-reporter

Stellt `GET /takuma-reporter` bereit. Der takuma manager ruft darüber Laravel-Version, PHP-Version, Umgebung und die Pakete aus `composer.lock` ab (Contract v1). Ungültiger Token, nicht erlaubte IP oder andere Methode als GET: Antwort ist dieselbe 404 wie bei einer unbekannten Route.

Voraussetzung: PHP 8.1+, Laravel 10 bis 13.

## Installation

Zugriff auf das private Repo läuft pro Server über einen eigenen Deploy-Key (read-only), nicht über ein GitHub-Token. Ein geleakter Key öffnet dann nur dieses eine Repo und lässt sich einzeln widerrufen.

1. Auf dem Server einen Key ohne Passphrase erzeugen: `ssh-keygen -t ed25519 -f ~/.ssh/takuma-reporter -N ""`
2. Den Public Key in GitHub unter Repo > Settings > Deploy keys eintragen, ohne Schreibrecht. Pro Server ein Key, Titel = Server/Kunde.
3. Git den Key fest zuweisen. Im Chroot (Shared Hosting) liest ssh das Home aus `/etc/passwd` statt `$HOME` und findet `~/.ssh` sonst nicht:

```bash
git config --global core.sshCommand "ssh -i $HOME/.ssh/takuma-reporter -o IdentitiesOnly=yes"
```

4. `composer.json` des Projekts. Ohne Token liefert GitHub kein ZIP. Composer fällt dann nicht von selbst auf Git zurück. Deshalb `preferred-install: source` für das Paket:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:takuma-OHG/laravel-reporter.git" }
],
"config": {
    "preferred-install": { "takuma/*": "source", "*": "dist" }
}
```

```bash
composer require takuma/laravel-reporter:^1.0
```

Immer eine Versions-Einschränkung wie `^1.0` verwenden, nie `dev-main`. Sonst landet jeder Push auf `main` beim nächsten `composer update` auf dem Kundenserver.

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
