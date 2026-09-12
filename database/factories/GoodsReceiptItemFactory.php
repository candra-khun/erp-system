<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptItem>
 */
class GoodsReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
