# Kumpara for PHP

[![CI](https://github.com/nufel-web-hosting/kumpara-php/actions/workflows/ci.yml/badge.svg)](https://github.com/nufel-web-hosting/kumpara-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/kumpara/kumpara-php.svg)](https://packagist.org/packages/kumpara/kumpara-php)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Offisielt PHP-bibliotek for [Kumpara](https://kumpara.no): lag, send og følg opp norske fakturaer med KID og PDF fra din egen kode.

*The official PHP SDK for Kumpara, the Norwegian invoicing API: create and send invoices with KID, PDF and webhooks from your own code.*

- Kunder, varer, fakturaer og kreditnotaer
- KID med MOD10 eller MOD11, fortløpende fakturanummer uten hull
- PDF og e-post til kunden
- Webhooks med signaturkontroll
- Kontoer for kundene dine (`tenants`)
- Trygge nye forsøk: `externalRef` gjør at ingenting blir dobbelt
- Ingen avhengigheter utover PHP 8.1+ med `curl` og `json`

Kumpara flytter aldri penger. «Betalt» er en markering du eller systemet ditt gjør.

## Installer

```bash
composer require kumpara/kumpara-php
```

## Kom i gang

Lag en nøkkel i Kumpara under **Utviklerverktøy → API**. Start med en **Test**-nøkkel (`kp_test_...`): egen testkonto, «TEST» på PDF-en, og ingen e-post går til kunder.

Legg nøkkelen i miljøvariabelen `KUMPARA_API_KEY`. Aldri i koden, og aldri i nettleseren.

```php
require 'vendor/autoload.php';

$kumpara = new Kumpara\Client();   // leser KUMPARA_API_KEY

$customer = $kumpara->customers()->create([
    'type' => 'company',
    'name' => 'Fjord Design AS',
    'orgNumber' => '923609016',
    'email' => 'post@fjorddesign.no',
    'address' => 'Strandgata 1',
    'postalCode' => '5003',
    'city' => 'Bergen',
    'externalRef' => 'kunde-42',        // din egen id: samme verdi igjen endrer samme kunde
]);

$invoice = $kumpara->invoices()->create([
    'customerId' => $customer['id'],
    'lines' => [
        ['name' => 'Abonnement Pro, desember', 'quantity' => 1, 'unitPrice' => 14900],
        ['name' => 'Oppsett', 'quantity' => 2, 'unit' => 'time', 'unitPrice' => 95000],
    ],
    'dueDays' => 14,
    'externalRef' => 'ordre-1001',      // samme verdi igjen gir samme faktura tilbake, aldri en ny
    'send' => true,                     // e-post med PDF til kunden
]);

echo $invoice['invoiceNumber'], ' ', $invoice['kid'];
```

**Alle beløp er i øre, som hele tall.** 149 kr = `14900`.

Hvert kall gir `data`-delen av svaret fra API-et som en array. Feltene står i [dokumentasjonen](https://kumpara.no/docs).

## Kall

```php
$kumpara->me();                                  // kontoen nøkkelen virker i, og mode: test eller live
$kumpara->registry('923609016');                 // slå opp i Enhetsregisteret (org.nr. eller navn)

$kumpara->customers()->list(['q' => 'fjord', 'page' => 1]);
$kumpara->customers()->get(12);
$kumpara->customers()->update(12, ['email' => 'faktura@fjorddesign.no']);

$kumpara->products()->create(['name' => 'Timepris design', 'unit' => 'time', 'unitPrice' => 95000]);

$kumpara->invoices()->list(['filter' => 'overdue']);   // open, overdue, paid, credited, credit
$kumpara->invoices()->send(26);                          // eller send(26, 'regnskap@firma.no')
$kumpara->invoices()->markPaid(26, ['paidDate' => '2026-11-19']);
$kumpara->invoices()->markUnpaid(26);
$kumpara->invoices()->credit(26, 'Feil antall timer');   // kreditnota for hele fakturaen
file_put_contents('faktura.pdf', $kumpara->invoices()->pdfContents(26));
```

## Feil

Når API-et sier nei, kastes en `Kumpara\Exception\ApiException` eller en av underklassene. `getMessage()` er API-ets egen tekst (norsk, for mennesker). Bruk `getErrorCode()` og `getField()` i koden.

```php
use Kumpara\Exception\ConflictException;
use Kumpara\Exception\ValidationException;

try {
    $kumpara->invoices()->create($invoice);
} catch (ValidationException $e) {
    // 422: getField() sier hvilket felt, for eksempel "plan" når gratisfakturaene er brukt opp
} catch (ConflictException $e) {
    // 409: "settings" (avsenderopplysninger mangler) eller "numberStart" (første fakturanummer ikke valgt)
}
```

| Unntak | HTTP | Eksempel på `getErrorCode()` |
|---|---|---|
| `AuthenticationException` | 401 | `invalid_api_key`, `unauthorized` |
| `PaymentException` | 402, 502 | `payment_failed`, `payment_provider` |
| `PermissionException` | 403 | `missing_scope`, `not_allowed_for_api_key`, `account_closed` |
| `NotFoundException` | 404 | `not_found` |
| `ConflictException` | 409 | `conflict` |
| `ValidationException` | 422 | `validation_error` |
| `RateLimitException` | 429 | `rate_limited`, `too_many_invalid_keys` |
| `ServerException` | 5xx | `server_error` |
| `ConnectionException` | | Ikke noe svar (DNS, TLS, tidsavbrudd) |

### Nye forsøk

Biblioteket prøver igjen av seg selv (to ganger som standard) når det er trygt:

- `429 rate_limited`: venter så lenge `Retry-After` sier, opptil 10 sekunder.
- Nettverksfeil og `5xx`: bare for `GET`, og for `POST` med `externalRef` eller `ref`, fordi Kumpara da gir samme resultat tilbake i stedet for å lage noe nytt.

Bruk derfor alltid `externalRef` når du lager fakturaer.

```php
$kumpara = new Kumpara\Client(null, ['max_retries' => 0, 'timeout' => 15]);
```

## Webhooks

Legg til en https-adresse under **Utviklerverktøy → Webhooks** i Kumpara. Sjekk signaturen før du stoler på innholdet:

```php
use Kumpara\Exception\SignatureException;
use Kumpara\Webhook;

try {
    $event = Webhook::constructEvent(
        file_get_contents('php://input'),
        $_SERVER['HTTP_KUMPARA_SIGNATURE'] ?? null,
        getenv('KUMPARA_WEBHOOK_SECRET')
    );
} catch (SignatureException $e) {
    http_response_code(400);
    exit;
}

if ($event['type'] === 'invoice.paid') {
    $invoice = $event['data']['invoice'];
    // marker ordren $invoice['externalRef'] som betalt i systemet ditt
}
http_response_code(200);
```

Svar med `2xx` innen 5 sekunder. Samme hendelse kan i sjeldne tilfeller komme to ganger: bruk `$event['id']` for å se om du har behandlet den før.

## Kontoer for kundene dine

Lager du en app der kundene dine fakturerer, kan hver av dem få sin egen Kumpara-konto. Nøkkelen trenger rettigheten `tenants`.

```php
$tenant = $kumpara->tenants()->create([
    'ref' => 'kunde-42',                // din egen id: samme ref igjen gir samme konto
    'name' => 'Rørlegger Hansen AS',
    'orgNumber' => '923609016',
    'email' => 'post@hansen.no',
    'address' => 'Ny vei 9',
    'postalCode' => '5003',
    'city' => 'Bergen',
    'bankAccount' => '86011117947',
]);

$hansen = $kumpara->withTenant($tenant['id']);   // en kopi av klienten som jobber i den kontoen
$hansen->invoices()->create([...]);
```

Med en live-nøkkel er hver ny konto et eget Pro-abonnement. Les [dokumentasjonen](https://kumpara.no/docs#kontoer-for-kundene-dine) før du bruker det.

## Test og live

`$kumpara->isTestMode()` er `true` for en `kp_test_`-nøkkel. Bygg og test med den, og bytt til `kp_live_` når alt virker.

## AI-assistenter

Kumpara kan også brukes direkte fra Claude og ChatGPT med MCP: `https://mcp.kumpara.no`. Dokumentasjonen for AI-assistenter som skriver kode: [kumpara.no/llms.txt](https://kumpara.no/llms.txt).

## Utvikling

```bash
composer install
composer test
```

## Lisens

MIT. Se [LICENSE](LICENSE). Kumpara er laget av Nufel Web Hosting.
