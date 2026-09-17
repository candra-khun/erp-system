<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LoyaltyTier;
use App\Models\Customer;
use App\Models\CustomerLoyaltyProfile;
use App\Models\LoyaltyPoint;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty program service (PRD 4.6 — Program Loyalitas/Member, Should Have).
 * Points are earned per rupiah spent on completed sales; tiers upgrade
 * automatically based on lifetime points.
 */
class LoyaltyService
{
    /** Rp needed to earn 1 point (config-driven). */
    private int $pointsPerRupiahDivisor = 10000;

    public function __construct(private readonly ?int $pointsDivisor = null)
    {
        if ($pointsDivisor !== null && $pointsDivisor > 0) {
            $this->pointsPerRupiahDivisor = $pointsDivisor;
        } elseif (config('erp.loyalty.points_per_rupiah_divisor') > 0) {
            $this->pointsPerRupiahDivisor = (int) config('erp.loyalty.points_per_rupiah_divisor');
        }
    }

    /**
     * Award points for a completed sale. Skips walk-in (null customer)
     * and non-member customers. Safe to call inside an outer transaction.
     *
     * @return int points awarded (0 when skipped)
     */
    public function awardForSale(int $customerId, float $totalAmount, string $referenceType, int $referenceId, ?int $userId = null): int
    {
        $customer = Customer::find($customerId);

        if (! $customer || $customer->type !== 'member') {
            return 0;
        }

        $points = (int) floor($totalAmount / $this->pointsPerRupiahDivisor);

        if ($points <= 0) {
            return 0;
        }

        // Idempoten: lewati bila poin untuk referensi ini sudah pernah diberikan.
        $alreadyAwarded = LoyaltyPoint::where('customer_id', $customerId)
            ->where('type', 'earn')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();

        if ($alreadyAwarded) {
            return 0;
        }

        return $this->adjust(
            $customerId,
            'earn',
            $points,
            $referenceType,
            $referenceId,
            'Poin dari transaksi',
            $userId,
        );
    }

    /**
     * Redeem points (positive integer). Fails when balance insufficient.
     *
     * @throws \RuntimeException when points exceed the balance
     */
    public function redeem(int $customerId, int $points, string $referenceType, ?int $referenceId = null, ?int $userId = null, ?string $notes = null): int
    {
        if ($points <= 0) {
            throw new \RuntimeException('Jumlah poin untuk redeem harus lebih dari 0.');
        }

        return DB::transaction(function () use ($customerId, $points, $referenceType, $referenceId, $userId, $notes): int {
            $this->ensureProfileExists($customerId);

            $profile = CustomerLoyaltyProfile::where('customer_id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $profile->points_balance < $points) {
                throw new \RuntimeException('Poin tidak mencukupi.');
            }

            return $this->adjust(
                $customerId,
                'redeem',
                -$points,
                $referenceType,
                $referenceId,
                $notes ?? 'Redeem poin',
                $userId,
            );
        });
    }

    /**
     * Manual adjustment (admin correction / expiry).
     */
    public function adjust(int $customerId, string $type, int $points, ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null, ?int $userId = null): int
    {
        return DB::transaction(function () use ($customerId, $type, $points, $referenceType, $referenceId, $notes, $userId): int {
            // Pastikan baris profil ada sebelum di-lock (hindari race firstOrCreate).
            $this->ensureProfileExists($customerId);

            $profile = CustomerLoyaltyProfile::where('customer_id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            $newBalance = (int) $profile->points_balance + $points;

            if ($newBalance < 0) {
                throw new \RuntimeException('Poin tidak boleh minus.');
            }

            $newLifetime = (int) $profile->lifetime_points + max(0, $points);

            $profile->points_balance = $newBalance;
            $profile->lifetime_points = $newLifetime;

            $this->refreshTier($profile);

            $profile->save();

            LoyaltyPoint::create([
                'customer_id' => $customerId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            return $newBalance;
        });
    }

    /**
     * Buat profil loyalitas bila belum ada (di dalam transaksi, tanpa race).
     */
    private function ensureProfileExists(int $customerId): void
    {
        CustomerLoyaltyProfile::firstOrCreate(
            ['customer_id' => $customerId],
            ['tier' => LoyaltyTier::Bronze, 'points_balance' => 0, 'lifetime_points' => 0],
        );
    }

    /**
     * Upgrade tier based on lifetime points (never downgrades mid-cycle).
     */
    private function refreshTier(CustomerLoyaltyProfile $profile): void
    {
        $lifetime = (int) $profile->lifetime_points;

        $targetTier = match (true) {
            $lifetime >= LoyaltyTier::Gold->requiredPoints() => LoyaltyTier::Gold,
            $lifetime >= LoyaltyTier::Silver->requiredPoints() => LoyaltyTier::Silver,
            default => LoyaltyTier::Bronze,
        };

        if ($targetTier !== $profile->tier && $targetTier !== LoyaltyTier::Bronze) {
            $profile->tier = $targetTier;
            $profile->tier_achieved_at = now()->toDateString();
        }
    }

    /**
     * Discount percent for a customer's current tier (PRD 4.6 diskon khusus).
     */
    public function tierDiscountPercent(int $customerId): float
    {
        $profile = CustomerLoyaltyProfile::where('customer_id', $customerId)->first();

        return $profile?->tier->discountPercent() ?? 0.0;
    }
}
