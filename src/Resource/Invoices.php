<?php

declare(strict_types=1);

namespace Kumpara\Resource;

/**
 * Invoices and credit notes: /invoices. An issued invoice has the next number in the series and can never be
 * deleted; a mistake is fixed with credit(). Amounts in øre.
 */
final class Invoices extends Resource
{
    /**
     * @param array{filter?: ''|'open'|'overdue'|'paid'|'credited'|'credit', q?: string, page?: int} $params
     */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/invoices', $params);
    }

    public function get(int $id): array
    {
        return $this->client->request('GET', '/invoices/' . $id);
    }

    /**
     * Issues an invoice. Use externalRef (your order id): the same externalRef again gives the same invoice back,
     * never a new one and never a second e-mail, so a retry after a timeout is safe.
     *
     * @param array{customerId: int, lines: list<array<string, mixed>>, externalRef?: string, send?: bool,
     *     paid?: bool, dueDays?: int, yourRef?: string, ourRef?: string, note?: string, deliveryDate?: string} $invoice
     */
    public function create(array $invoice): array
    {
        return $this->client->request('POST', '/invoices', [], $invoice);
    }

    /** E-mails the invoice as PDF, to the customer or to $email. */
    public function send(int $id, ?string $email = null): array
    {
        return $this->client->request('POST', '/invoices/' . $id . '/send', [], $email === null ? [] : ['email' => $email]);
    }

    /**
     * Marks it as paid. Kumpara never moves money: this is your own record that the money came.
     *
     * @param array{paidDate?: string, paidRef?: string} $details
     */
    public function markPaid(int $id, array $details = []): array
    {
        return $this->client->request('POST', '/invoices/' . $id . '/paid', [], $details);
    }

    public function markUnpaid(int $id): array
    {
        return $this->client->request('POST', '/invoices/' . $id . '/unpaid', [], []);
    }

    /** Makes a credit note for the whole invoice. */
    public function credit(int $id, ?string $reason = null): array
    {
        return $this->client->request('POST', '/invoices/' . $id . '/credit', [], $reason === null ? [] : ['reason' => $reason]);
    }

    /**
     * The PDF as the API gives it.
     *
     * @return array{filename: string, contentType: string, size: int, base64: string}
     */
    public function pdf(int $id): array
    {
        return $this->client->request('GET', '/invoices/' . $id . '/pdf');
    }

    /** The PDF file itself, ready to save or send. */
    public function pdfContents(int $id): string
    {
        $pdf = $this->pdf($id);
        $bytes = base64_decode((string)($pdf['base64'] ?? ''), true);
        if ($bytes === false) {
            throw new \UnexpectedValueException('The PDF from Kumpara was not valid base64.');
        }

        return $bytes;
    }
}
