<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Supplier\Application\Port\SupplierClassificationRepository;

final readonly class ManageSupplierClassification
{
    public function __construct(private SupplierClassificationRepository $classifications) {}

    /** @return list<array<string,mixed>> */
    public function list(string $type): array
    {
        return $this->classifications->list($type);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function create(string $type, array $data): array
    {
        return $this->classifications->create($type, $data);
    }

    /** @return array<string,mixed> */
    public function setActive(string $type, int $id, bool $active): array
    {
        return $this->classifications->setActive($type, $id, $active);
    }

    /** @return array<string,mixed> */
    public function get(int $supplierId): array
    {
        return $this->classifications->get($supplierId);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function assign(int $supplierId, array $data, string $actor): array
    {
        return $this->classifications->assign($supplierId, $data, $actor);
    }
}
