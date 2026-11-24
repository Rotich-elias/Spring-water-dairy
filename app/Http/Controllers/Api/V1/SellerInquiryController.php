<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InquiryUpdateRequest;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerInquiryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('inquiries.view');

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['new', 'contacted', 'closed'])],
        ]);

        $inquiries = ContactInquiry::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ContactInquiry $inquiry) => $this->present($inquiry));

        return response()->json(['data' => $inquiries]);
    }

    public function show(ContactInquiry $inquiry)
    {
        $this->authorize('inquiries.view');

        return response()->json(['data' => $this->present($inquiry)]);
    }

    public function update(InquiryUpdateRequest $request, ContactInquiry $inquiry)
    {
        $inquiry->update($request->validated());

        return response()->json(['data' => $this->present($inquiry->fresh())]);
    }

    public function destroy(ContactInquiry $inquiry)
    {
        $this->authorize('inquiries.manage');

        $inquiry->delete();

        return response()->json(['data' => ['id' => $inquiry->id, 'deleted' => true]]);
    }

    /**
     * @return array{id: int, name: string, farm_name: ?string, email: string, phone: ?string, message: string, status: string, created_at: ?string, updated_at: ?string}
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
            'updated_at' => $inquiry->updated_at?->toISOString(),
        ];
    }
}
