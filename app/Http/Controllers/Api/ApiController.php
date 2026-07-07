<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    /**
     * Return a success JSON response.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a paginated JSON response.
     */
    protected function paginated(mixed $resource, string $message = 'Success'): JsonResponse
    {
        if ($resource instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection
            || $resource instanceof \Illuminate\Http\Resources\Json\ResourceCollection) {
            return $resource->additional([
                'success' => true,
                'message' => $message,
            ])->response();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
        ]);
    }
}
