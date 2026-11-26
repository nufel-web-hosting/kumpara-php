<?php
/**
 * A webhook endpoint. Put it at a public https address, add that address in Kumpara under
 * Utviklerverktøy → Webhooks, and set KUMPARA_WEBHOOK_SECRET to the webhook's secret (whsec_...).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kumpara\Exception\SignatureException;
use Kumpara\Webhook;

try {
    $event = Webhook::constructEvent(
        (string)file_get_contents('php://input'),
        $_SERVER['HTTP_KUMPARA_SIGNATURE'] ?? null,
        (string)getenv('KUMPARA_WEBHOOK_SECRET')
    );
} catch (SignatureException $e) {
    http_response_code(400);
    exit;
}

// The same event can (rarely) come twice: remember $event['id'] and skip one you have handled.
$invoice = $event['data']['invoice'] ?? [];

switch ($event['type']) {
    case 'invoice.paid':
        // Mark your order $invoice['externalRef'] as paid
        break;
    case 'invoice.overdue':
        // Remind the customer, or pause the service
        break;
    case 'invoice.credited':
        // Undo the order
        break;
}

// Answer 2xx within 5 seconds; do slow work after answering or in a queue
http_response_code(200);
