<?php
/**
 * Creates (or finds again) a customer and an invoice in your TEST account.
 *
 *   KUMPARA_API_KEY=kp_test_... php examples/create-invoice.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kumpara\Exception\ApiException;

$kumpara = new Kumpara\Client();
if (!$kumpara->isTestMode()) {
    exit("This example only runs with a kp_test_ key.\n");
}

try {
    $customer = $kumpara->customers()->create([
        'type' => 'person',
        'name' => 'Kari Nordmann',
        'email' => 'kari@example.no',
        'externalRef' => 'example-customer-1',
    ]);

    $invoice = $kumpara->invoices()->create([
        'customerId' => $customer['id'],
        'lines' => [
            ['name' => 'Konsultasjon', 'quantity' => 1.5, 'unit' => 'time', 'unitPrice' => 95000],
        ],
        'externalRef' => 'example-order-1',   // run it again: you get the same invoice back
        'send' => true,                       // test account: no e-mail leaves Kumpara
    ]);

    printf("Invoice %s, KID %s, %s\n", $invoice['invoiceNumber'] ?? '?', $invoice['kid'] ?? '?', $invoice['totals']['incVatText'] ?? '');
    file_put_contents(__DIR__ . '/invoice.pdf', $kumpara->invoices()->pdfContents((int)$invoice['id']));
    echo "Saved examples/invoice.pdf\n";
} catch (ApiException $e) {
    fprintf(STDERR, "%s (%s%s)\n", $e->getMessage(), $e->getErrorCode(), $e->getField() ? ', field ' . $e->getField() : '');
    exit(1);
}
