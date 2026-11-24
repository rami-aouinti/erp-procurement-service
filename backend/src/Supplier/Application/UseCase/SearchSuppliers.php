<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;
use Throwable;

final readonly class SearchSuppliers
{
    public function __construct(
        private SupplierSearchIndex $searchIndex,
        private SupplierListCache $cache,
        private SupplierRepository $suppliers,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function execute(string $query = '', ?string $status = null): array
    {
        $query = trim($query);
        $status = $status !== null && $status !== '' ? strtoupper(trim($status)) : null;
        $cacheKey = 'search:' . hash('sha256', mb_strtolower($query) . '|' . ($status ?? 'ALL'));

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $results = $this->searchIndex->search($query, $status);

            // Bootstrap an empty index from Oracle on the first unfiltered listing.
            if ($results === [] && $query === '' && $status === null) {
                $allSuppliers = $this->suppliers->findAll();
                if ($allSuppliers !== []) {
                    $this->searchIndex->reindex($allSuppliers);
                    $results = $this->searchIndex->search('', null);
                }
            }
        } catch (Throwable $exception) {
            error_log('Elasticsearch supplier search failed; using Oracle fallback: ' . $exception->getMessage());
            $results = array_values(array_filter(
                array_map($this->toArray(...), $this->suppliers->findAll()),
                static function (array $supplier) use ($query, $status): bool {
                    if ($status !== null && $supplier['status'] !== $status) {
                        return false;
                    }
                    if ($query === '') {
                        return true;
                    }
                    foreach (['name', 'email', 'phone', 'taxNumber', 'contactPerson', 'city', 'street', 'postalCode'] as $field) {
                        if (str_contains(mb_strtolower((string) ($supplier[$field] ?? '')), mb_strtolower($query))) {
                            return true;
                        }
                    }
                    return false;
                },
            ));
        }

        $this->cache->set($cacheKey, $results, 60);

        return $results;
    }

    /** @return array<string, mixed> */
    private function toArray(Supplier $supplier): array
    {
        return [
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
        ];
    }
}
