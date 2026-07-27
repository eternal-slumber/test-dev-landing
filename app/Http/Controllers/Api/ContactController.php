<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

final class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService,
    ) {}

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->contactService->create(
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'id' => $contact->id,
                'status' => 'accepted',
            ],
            'message' => 'Обращение принято',
        ], 201);
    }
}
