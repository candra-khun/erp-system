<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCommunication;
use App\Models\CustomerLoyaltyProfile;
use App\Models\LoyaltyPoint;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyaltyService) {}

    /**
     * Loyalty profile + recent point logs for a customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $profile = CustomerLoyaltyProfile::firstOrCreate(
            ['customer_id' => $customer->id],
            ['points_balance' => 0, 'lifetime_points' => 0],
        );

        $logs = LoyaltyPoint::where('customer_id', $customer->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'customer' => $customer->only(['id', 'name', 'type']),
                'tier' => $profile->tier,
                'tier_discount_percent' => $profile->tier->discountPercent(),
                'points_balance' => (int) $profile->points_balance,
                'lifetime_points' => (int) $profile->lifetime_points,
                'recent_points' => $logs,
            ],
        ]);
    }

    /**
     * Redeem points for a customer.
     */
    public function redeem(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $balance = $this->loyaltyService->redeem(
                $customer->id,
                (int) $validated['points'],
                'manual',
                null,
                $request->user()?->id,
                $validated['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['points_balance' => $balance]]);
    }

    /**
     * Record a customer communication (PRD 4.6 riwayat komunikasi).
     */
    public function storeCommunication(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:phone,whatsapp,email,visit,other',
            'subject' => 'nullable|string|max:200',
            'summary' => 'required|string|max:2000',
            'follow_up_notes' => 'nullable|string|max:1000',
            'follow_up_date' => 'nullable|date',
        ]);

        $communication = CustomerCommunication::create([
            ...$validated,
            'customer_id' => $customer->id,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $communication], 201);
    }

    /**
     * List communications for a customer.
     */
    public function communications(Customer $customer): JsonResponse
    {
        $communications = CustomerCommunication::where('customer_id', $customer->id)
            ->with('creator:id,name')
            ->latest()
            ->paginate(25);

        return response()->json(['data' => $communications]);
    }
}
