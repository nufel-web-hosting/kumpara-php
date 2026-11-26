<?php

declare(strict_types=1);

namespace Kumpara\Resource;

/**
 * Accounts for your customers: /tenants. The key needs the `tenants` scope. Work inside one with
 * $client->withTenant($id).
 *
 * With a live key, a new account is its own Pro subscription, charged to the card you added in Kumpara.
 */
final class Tenants extends Resource
{
    public function list(): array
    {
        return $this->client->request('GET', '/tenants');
    }

    public function get(int $id): array
    {
        return $this->client->request('GET', '/tenants/' . $id);
    }

    /**
     * Creates an account. `ref` is your own id for it: the same ref again gives the same account back (and is not
     * charged again).
     *
     * @param array{ref: string, name: string, orgNumber?: string, email?: string, address?: string,
     *     postalCode?: string, city?: string, bankAccount?: string, kidMode?: 'mod10'|'mod11'|'message',
     *     vatRegistered?: bool, defaultVatRate?: int, phone?: string, website?: string, paymentTermsDays?: int,
     *     invoiceNote?: string, invoiceColor?: string, invoiceStart?: int} $tenant
     */
    public function create(array $tenant): array
    {
        return $this->client->request('POST', '/tenants', [], $tenant);
    }

    /** Changes only the fields you send. */
    public function update(int $id, array $changes): array
    {
        return $this->client->request('POST', '/tenants/' . $id, [], $changes);
    }

    /** Ends the account. A live account lasts out the paid period; a test account is closed. */
    public function release(int $id): array
    {
        return $this->client->request('POST', '/tenants/' . $id . '/release', [], []);
    }

    /** @param string $dataUrl data:image/png;base64,... (PNG, JPG, WebP or GIF, up to 2 MB) */
    public function setLogo(int $id, string $dataUrl): array
    {
        return $this->client->request('POST', '/tenants/' . $id . '/logo', [], ['logo' => $dataUrl]);
    }

    /** Reads an image file and sets it as the logo. */
    public function setLogoFromFile(int $id, string $path): array
    {
        $info = @getimagesize($path);
        $bytes = @file_get_contents($path);
        if ($info === false || $bytes === false) {
            throw new \InvalidArgumentException('Not a readable image: ' . $path);
        }

        return $this->setLogo($id, 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes));
    }

    public function removeLogo(int $id): array
    {
        return $this->client->request('POST', '/tenants/' . $id . '/logo/remove', [], []);
    }

    /** Sends your customer a link (valid 14 days) to take over the account. */
    public function transfer(int $id, string $email): array
    {
        return $this->client->request('POST', '/tenants/' . $id . '/transfer', [], ['email' => $email]);
    }

    public function withdrawTransfer(int $id): array
    {
        return $this->client->request('POST', '/tenants/' . $id . '/transfer/withdraw', [], []);
    }
}
