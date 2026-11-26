<?php

declare(strict_types=1);

namespace Kumpara\Resource;

/**
 * Customers: GET/POST /customers.
 */
final class Customers extends Resource
{
    /**
     * @param array{q?: string, page?: int} $params
     */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/customers', $params);
    }

    public function get(int $id): array
    {
        return $this->client->request('GET', '/customers/' . $id);
    }

    /**
     * Creates a customer. With externalRef (your own id for the customer), the same externalRef again updates
     * that customer instead: safe to run more than once.
     *
     * @param array{type: 'company'|'person', name: string, orgNumber?: string, email?: string, address?: string,
     *     postalCode?: string, city?: string, country?: string, contactName?: string, phone?: string, note?: string,
     *     externalRef?: string} $customer
     */
    public function create(array $customer): array
    {
        return $this->client->request('POST', '/customers', [], $customer);
    }

    public function update(int $id, array $changes): array
    {
        return $this->client->request('POST', '/customers/' . $id, [], $changes);
    }
}
