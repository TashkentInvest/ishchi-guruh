<?php

namespace App\Http\Controllers;

use App\Services\LargeDatasetCacheBuildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetProcessingController extends Controller
{
    public function index(LargeDatasetCacheBuildService $service)
    {
        return view('processing.cache_builder', [
            'initialState' => $service->progress(false),
        ]);
    }

    public function start(Request $request, LargeDatasetCacheBuildService $service): JsonResponse
    {
        $chunkSize = $request->integer('chunk_size');
        $state = $service->start($chunkSize);

        if (!empty($state['already_running'])) {
            return response()->json($state, 409);
        }

        return response()->json($state, 202);
    }

    public function progress(Request $request, LargeDatasetCacheBuildService $service): JsonResponse
    {
        $advance = $request->boolean('advance', true);

        return response()->json($service->progress($advance));
    }

    public function reset(LargeDatasetCacheBuildService $service): JsonResponse
    {
        return response()->json($service->reset());
    }
}
