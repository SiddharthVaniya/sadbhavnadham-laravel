<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonationOrder;
use App\Models\DonationTelecallerNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDonationTelecallerNoteController extends Controller
{
    public function index(DonationOrder $donationOrder): JsonResponse
    {
        $this->authorize('view', $donationOrder);

        return response()->json($this->payload($donationOrder));
    }

    public function store(Request $request, DonationOrder $donationOrder): JsonResponse
    {
        $this->authorize('view', $donationOrder);

        $data = $request->validate([
            'speaker' => ['required', Rule::in([
                DonationTelecallerNote::SPEAKER_TELECALLER,
                DonationTelecallerNote::SPEAKER_DONOR,
            ])],
            'message' => ['required', 'string', 'max:2000'],
            'outcome' => ['nullable', Rule::in(array_keys(DonationTelecallerNote::OUTCOMES))],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        DonationTelecallerNote::create([
            'donation_order_id' => $donationOrder->id,
            'donor_id' => $donationOrder->donor_id,
            'user_id' => $user?->id,
            'telecaller_name' => (string) ($user?->name ?? ''),
            'speaker' => $data['speaker'],
            'message' => trim($data['message']),
            'outcome' => $data['outcome'] ?? null,
            'follow_up_at' => $data['follow_up_at'] ?? null,
        ]);

        return response()->json($this->payload($donationOrder), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DonationOrder $order): array
    {
        $notes = DonationTelecallerNote::query()
            ->where('donation_order_id', $order->id)
            ->orderBy('id')
            ->get()
            ->map(fn (DonationTelecallerNote $note): array => [
                'id' => $note->id,
                'speaker' => $note->speaker,
                'telecaller_name' => $note->telecaller_name,
                'message' => $note->message,
                'outcome' => $note->outcome,
                'outcome_label' => DonationTelecallerNote::OUTCOMES[$note->outcome] ?? null,
                'follow_up_at' => $note->follow_up_at?->format('d M Y, h:i A'),
                'created_at' => $note->created_at?->format('d M Y, h:i A'),
                'created_date' => $note->created_at?->format('d M Y'),
            ])
            ->values();

        return [
            'donor_name' => $order->donor_name,
            'donor_phone' => $order->donor_phone,
            'status' => $order->status,
            'amount' => (float) $order->total_amount,
            'outcomes' => collect(DonationTelecallerNote::OUTCOMES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values(),
            'notes' => $notes,
        ];
    }
}
