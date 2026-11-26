<?php

declare(strict_types=1);

namespace Kumpara\Resource;

/**
 * Products: GET/POST /products. Prices in øre.
 */
final class Products extends Resource
{
    /**
     * @param array{q?: string, page?: int} $params
     */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/products', $params);
    }

    public function get(int $id): array
    {
        return $this->client->request('GET', '/products/' . $id);
    }

    /**
     * @param array{name: string, unitPrice?: int, unit?: string, description?: string, productNumber?: string,
     *     vatRate?: int, priceIncludesVat?: bool} $product
     */
    public function create(array $product): array
    {
        return $this->client->request('POST', '/products', [], $product);
    }

    public function update(int $id, array $changes): array
    {
        return $this->client->request('POST', '/products/' . $id, [], $changes);
    }
}
