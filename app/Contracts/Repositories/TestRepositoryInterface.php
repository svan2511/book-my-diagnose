<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TestRepositoryInterface
{
    public function catalog(array $filters): LengthAwarePaginator;

    public function findActive(int|string $idOrUuid): ?\App\Models\LabTest;
}
