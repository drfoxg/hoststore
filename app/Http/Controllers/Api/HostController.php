<?php

namespace App\Http\Controllers\Api;

use App\Enums\OperationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexHostRequest;
use App\Http\Requests\RenameHostRequest;
use App\Http\Requests\StoreHostRequest;
use App\Http\Resources\HostResource;
use App\Http\Resources\OperationResource;
use App\Jobs\RenameHostJob;
use App\Models\Host;
use App\Models\Operation;
use Illuminate\Http\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class HostController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexHostRequest $request)
    {
        $query = Host::query();

        // Поиск по hostname (ILIKE, использует pg_trgm) или точному IP
        if ($search = $request->searchQuery()) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('hostname ILIKE ?', ['%' . $search . '%']);

                if (filter_var($search, FILTER_VALIDATE_IP)) {
                    $q->orWhereRaw('ip = ?::inet', [$search]);
                }
            });
        }

        // Keyset пагинация
        if ($request->useKeyset()) {
            return $this->keysetPaginate($query, $request);
        }

        // Обычная пагинация
        $hosts = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->pageSize());

        return HostResource::collection($hosts);
    }

    private function keysetPaginate($query, IndexHostRequest $request)
    {
        $size = $request->pageSize();

        // Определяем направление и курсор
        if ($after = $request->cursorAfter()) {
            $cursor = Host::find($after);
            if ($cursor) {
                $query->where(function ($q) use ($cursor) {
                    $q->where('created_at', '<', $cursor->created_at)
                      ->orWhere(function ($q2) use ($cursor) {
                          $q2->where('created_at', '=', $cursor->created_at)
                             ->where('id', '<', $cursor->id);
                      });
                });
            }
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        } elseif ($before = $request->cursorBefore()) {
            $cursor = Host::find($before);
            if ($cursor) {
                $query->where(function ($q) use ($cursor) {
                    $q->where('created_at', '>', $cursor->created_at)
                      ->orWhere(function ($q2) use ($cursor) {
                          $q2->where('created_at', '=', $cursor->created_at)
                             ->where('id', '>', $cursor->id);
                      });
                });
            }
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        $hosts = $query->limit($size + 1)->get();

        // Проверяем есть ли следующая страница
        $hasMore = $hosts->count() > $size;
        if ($hasMore) {
            $hosts = $hosts->take($size);
        }

        // Если запрос был "before", разворачиваем результат
        if ($request->cursorBefore()) {
            $hosts = $hosts->reverse()->values();
        }

        $first = $hosts->first();
        $last = $hosts->last();

        return response()->json([
            'data' => HostResource::collection($hosts),
            'meta' => [
                'per_page' => $size,
                'has_more' => $hasMore,
            ],
            'cursors' => [
                'before' => $first?->id,
                'after' => $last?->id,
            ],
        ]);
    }

    public function store(StoreHostRequest $request)
    {
        $host = Host::create([
            'hostname' => $request->validated('hostname'),
            'ip' => $request->validated('ip'),
            'tags' => $request->validated('tags', []),
        ]);

        return HostResource::make($host)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function rename(RenameHostRequest $request, Host $host)
    {
        $this->authorize('rename', $host);

        $idempotencyKey = $request->idempotencyKey();

        // Идемпотентность: вернуть существующую операцию
        $existing = Operation::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json(
                ['operation_id' => $existing->id],
                Response::HTTP_ACCEPTED
            );
        }

        $operation = Operation::create([
            'type' => OperationType::Rename,
            'host_id' => $host->id,
            'payload' => ['new_hostname' => $request->validated('new_hostname')],
            'idempotency_key' => $idempotencyKey,
        ]);

        RenameHostJob::dispatch($operation);

        return response()->json(
            ['operation_id' => $operation->id],
            Response::HTTP_ACCEPTED
        );
    }
}
