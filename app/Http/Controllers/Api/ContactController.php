<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;

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

        Context::add('contact_id', $contact->id);

        return response()->json([
            'data' => [
                'id' => $contact->id,
                'status' => 'accepted',
            ],
            'message' => 'Обращение принято',
        ], 201);
    }
}
