<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactInquiryStoreRequest;
use App\Models\ContactInquiry;

class ContactInquiryController extends Controller
{
    public function store(ContactInquiryStoreRequest $request)
    {
        $inquiry = ContactInquiry::create(array_merge($request->validated(), [
            'status' => 'new',
        ]));

        return response()->json(['data' => $this->present($inquiry)], 201);
    }

    /**
     * @return array{id: int, name: string, farm_name: ?string, email: string, phone: ?string, message: string, status: string, created_at: ?string}
     */
    private function present(ContactInquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'name' => $inquiry->name,
            'farm_name' => $inquiry->farm_name,
            'email' => $inquiry->email,
            'phone' => $inquiry->phone,
            'message' => $inquiry->message,
            'status' => $inquiry->status,
            'created_at' => $inquiry->created_at?->toISOString(),
        ];
    }
}
