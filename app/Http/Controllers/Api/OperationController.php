<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Http\Resources\HostResource;
use App\Models\Operation;

class OperationController extends Controller
{
    public function show(Operation $operation)
    {
        return response()->json([
            'status' => $operation->status->value,
            'error' => $operation->error,
            'host' => new HostResource($operation->host),
        ]);
    }
}
