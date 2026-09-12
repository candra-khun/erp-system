<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'base_unit' => $this->whenLoaded('baseUnit'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
