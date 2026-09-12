<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product Categories (hierarchical)
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Product Units (satuan)
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');       // e.g., "pcs", "dus", "kg"
            $table->string('symbol');     // e.g., "pcs", "box", "kg"
            $table->decimal('conversion_factor', 15, 4)->default(1); // relative to base unit
            $table->foreignId('base_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->boolean('is_base')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->string('barcode')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_category_id');
            $table->index('is_active');
        });

        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->integer('payment_terms_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['general', 'member', 'reseller'])->default('general');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->integer('credit_limit')->default(0);
            $table->integer('payment_terms_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Warehouses / Branches
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['warehouse', 'branch', 'store'])->default('warehouse');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('phone')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('product_categories');
    }
};
