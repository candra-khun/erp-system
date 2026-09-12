<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ar_number' => $this->ar_number,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'warehouse_id' => $this->warehouse_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
