<?php

namespace App\Repositories;

use App\Contracts\Repositories\TestRepositoryInterface;
use App\Models\LabTest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentTestRepository implements TestRepositoryInterface
{
    public function catalog(array $filters): LengthAwarePaginator
    {
        $q = LabTest::with('category')->active()->homeCollectible();

        if (! empty($filters['search'])) {
            $s = '%'.str_replace(['%', '_'], '', $filters['search']).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('code', 'like', $s));
        }
        if (! empty($filters['category_id'])) {
            $q->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['category_slug'])) {
            $q->whereHas('category', fn ($w) => $w->where('slug', $filters['category_slug']));
        }
        if (isset($filters['max_price'])) {
            $q->where('price', '<=', $filters['max_price']);
        }
        if (isset($filters['min_price'])) {
            $q->where('price', '>=', $filters['min_price']);
        }

        $sort = $filters['sort'] ?? '-created_at';
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        $q->orderBy(in_array($col, ['price', 'name', 'created_at', 'turnaround_hours'], true) ? $col : 'created_at', $dir);

        return $q->paginate(min($filters['per_page'] ?? 20, 100));
    }

    public function findActive(int|string $idOrUuid): ?LabTest
    {
        return LabTest::with('category')->active()
            ->where(fn ($w) => is_numeric($idOrUuid)
                ? $w->where('id', $idOrUuid)
                : $w->where('uuid', $idOrUuid))
            ->first();
    }
}
