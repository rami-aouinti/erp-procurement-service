<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Search;

use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Model\Supplier;
use RuntimeException;

final readonly class ElasticsearchSupplierSearchIndex implements SupplierSearchIndex
{
    private const INDEX = 'suppliers';

    public function __construct(private string $url)
    {
    }

    public function search(string $query, ?string $status = null, int $limit = 500): array
    {
        $this->ensureIndex();
        $filters = [];
        if ($status !== null) {
            $filters[] = ['term' => ['status' => $status]];
        }

        $body = [
            'size' => min(500, max(1, $limit)),
            'query' => $query === ''
                ? ($filters === [] ? ['match_all' => (object) []] : ['bool' => ['filter' => $filters]])
                : ['bool' => [
                    'must' => [[
                        'multi_match' => [
                            'query' => $query,
                            'type' => 'best_fields',
                            'fields' => ['name^4', 'email^3', 'taxNumber^3', 'contactPerson^2', 'city^2', 'phone', 'street', 'postalCode', 'country'],
                            'fuzziness' => 'AUTO',
                            'operator' => 'and',
                        ],
                    ]],
                    'filter' => $filters,
                ]],
            'sort' => [['name.keyword' => ['order' => 'asc']]],
        ];

        $response = $this->request('POST', '/' . self::INDEX . '/_search', $body);
        $hits = $response['hits']['hits'] ?? [];
        if (!is_array($hits)) {
            return [];
        }

        $validHits = array_filter(
            $hits,
            static fn (mixed $hit): bool => is_array($hit) && isset($hit['_source']) && is_array($hit['_source']),
        );

        return array_values(array_map(
            static fn (array $hit): array => $hit['_source'],
            $validHits,
        ));
    }

    public function index(Supplier $supplier): void
    {
        $this->ensureIndex();
        $this->request('PUT', '/' . self::INDEX . '/_doc/' . $supplier->id() . '?refresh=wait_for', [
            'id' => $supplier->id(),
            'name' => $supplier->name(),
            'email' => $supplier->email(),
            'phone' => $supplier->phone(),
            'taxNumber' => $supplier->taxNumber(),
            'status' => $supplier->status(),
            'contactPerson' => $supplier->contactPerson(),
            'street' => $supplier->street(),
            'city' => $supplier->city(),
            'postalCode' => $supplier->postalCode(),
            'country' => $supplier->country(),
            'createdAt' => $supplier->createdAt()->format(DATE_ATOM),
        ]);
    }

    public function delete(int $supplierId): void
    {
        $this->ensureIndex();
        $this->request('DELETE', '/' . self::INDEX . '/_doc/' . $supplierId . '?refresh=wait_for', null, [200, 404]);
    }

    public function reindex(iterable $suppliers): int
    {
        $this->ensureIndex();
        $this->request('POST', '/' . self::INDEX . '/_delete_by_query?refresh=true&conflicts=proceed', [
            'query' => ['match_all' => (object) []],
        ]);
        $count = 0;
        foreach ($suppliers as $supplier) {
            $this->index($supplier);
            ++$count;
        }
        return $count;
    }

    private function ensureIndex(): void
    {
        $this->request('PUT', '/' . self::INDEX, [
            'settings' => ['number_of_shards' => 1, 'number_of_replicas' => 0],
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                    'email' => ['type' => 'text'],
                    'phone' => ['type' => 'text'],
                    'taxNumber' => ['type' => 'text'],
                    'status' => ['type' => 'keyword'],
                    'contactPerson' => ['type' => 'text'],
                    'street' => ['type' => 'text'],
                    'city' => ['type' => 'text'],
                    'postalCode' => ['type' => 'text'],
                    'country' => ['type' => 'keyword'],
                    'createdAt' => ['type' => 'date'],
                ],
            ],
        ], [200, 400]);
    }

    /** @param array<string, mixed>|null $payload
     *  @param list<int> $acceptedStatuses
     *  @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, array $acceptedStatuses = [200, 201]): array
    {
        $headers = "Content-Type: application/json\r\n";
        $options = [
            'http' => [
                'method' => $method,
                'header' => $headers,
                'ignore_errors' => true,
                'timeout' => 3,
            ],
        ];
        if ($payload !== null) {
            $options['http']['content'] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $context = stream_context_create($options);
        $response = @file_get_contents(rtrim($this->url, '/') . $path, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($response === false || !in_array($status, $acceptedStatuses, true)) {
            throw new RuntimeException(sprintf('Elasticsearch request failed (%s %s): HTTP %d %s', $method, $path, $status, is_string($response) ? $response : 'no response'));
        }

        if ($response === '') {
            return [];
        }
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Elasticsearch returned an invalid JSON payload.');
        }

        return $decoded;
    }
}
