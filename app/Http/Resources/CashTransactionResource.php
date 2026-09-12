<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'warehouse_id' => $this->warehouse_id,
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'transaction_date' => $this->transaction_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
