<?php

namespace App\Domain\Audit\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditService
{
    /**
     * List audit log entries, newest first.
     *
     * @param  array{user_id?: int, action?: string, from?: string, to?: string, page?: int, per_page?: int}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user')->orderByDesc('created_at');

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Get distinct actions used in the audit log.
     *
     * @return Collection<int, string>
     */
    public function actions(): Collection
    {
        return AuditLog::query()
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
    }
}
