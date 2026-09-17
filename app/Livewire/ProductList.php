<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Produk'])]
class ProductList extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $sku = '';

    public string $name = '';

    public string $product_category_id = '';

    public string $base_unit_id = '';

    public string $barcode = '';

    public string $description = '';

    public string $purchase_price = '0';

    public string $selling_price = '0';

    public string $min_stock = '0';

    public string $reorder_point = '0';

    public bool $is_active = true;

    public bool $reorder_alert_enabled = false;

    public $image = null;

    public ?string $existingImage = null;

    public int $labelCopies = 1;

    public ?int $previewId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editId', 'sku', 'name', 'product_category_id', 'base_unit_id', 'barcode', 'description', 'purchase_price', 'selling_price', 'min_stock', 'reorder_point', 'reorder_alert_enabled', 'image', 'existingImage']);
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->editId = $product->id;
        $this->sku = $product->sku;
        $this->name = $product->name;
        $this->product_category_id = (string) $product->product_category_id;
        $this->base_unit_id = (string) $product->base_unit_id;
        $this->barcode = (string) $product->barcode;
        $this->description = (string) $product->description;
        $this->purchase_price = (string) $product->purchase_price;
        $this->selling_price = (string) $product->selling_price;
        $this->min_stock = (string) ($product->min_stock ?? 0);
        $this->reorder_point = (string) ($product->reorder_point ?? 0);
        $this->is_active = (bool) $product->is_active;
        $this->reorder_alert_enabled = (bool) $product->reorder_alert_enabled;
        $this->image = null;
        $this->existingImage = $product->image_path;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function updatedImage(): void
    {
        $this->validateOnly('image', [
            'image' => 'nullable|image|max:2048',
        ]);
    }

    public function removeImage(): void
    {
        if ($this->editId && $this->existingImage) {
            Storage::disk('public')->delete($this->existingImage);
            Product::findOrFail($this->editId)->update(['image_path' => null]);
        }

        $this->image = null;
        $this->existingImage = null;
    }

    public function save(): void
    {
        $rules = [
            'sku' => 'required|string|max:50|unique:products,sku'.($this->editId ? ",{$this->editId}" : '').',id',
            'name' => 'required|string|max:255',
            'product_category_id' => 'required|exists:product_categories,id',
            'base_unit_id' => 'required|exists:product_units,id',
            'barcode' => 'nullable|string|max:100|unique:products,barcode'.($this->editId ? ",{$this->editId}" : '').',id',
            'description' => 'nullable|string|max:1000',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'reorder_point' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'reorder_alert_enabled' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ];

        if (! $this->editId && $this->barcode === '') {
            unset($rules['barcode']);
        }

        $validated = $this->validate($rules);

        $data = [
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'product_category_id' => (int) $validated['product_category_id'],
            'base_unit_id' => (int) $validated['base_unit_id'],
            'barcode' => $this->barcode !== '' ? $this->barcode : null,
            'description' => $this->description !== '' ? $this->description : null,
            'purchase_price' => (float) $validated['purchase_price'],
            'selling_price' => (float) $validated['selling_price'],
            'min_stock' => (float) $validated['min_stock'],
            'reorder_point' => (float) $validated['reorder_point'],
            'is_active' => $validated['is_active'],
            'reorder_alert_enabled' => $validated['reorder_alert_enabled'],
        ];

        if ($this->image) {
            $data['image_path'] = $this->image->store('products', 'public');
        }

        if ($this->editId) {
            $product = Product::findOrFail($this->editId);

            if ($this->image && $product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $product->update($data);
            session()->flash('success', 'Produk berhasil diperbarui.');
        } else {
            Product::create($data);
            session()->flash('success', 'Produk berhasil dibuat.');
        }

        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $product = Product::findOrFail($id);

        if ($product->stocks()->where('quantity', '>', 0)->exists()) {
            session()->flash('error', 'Produk masih memiliki stok. Kosongkan stok terlebih dahulu.');

            return;
        }

        $product->delete();
        session()->flash('success', 'Produk berhasil dihapus (soft delete).');
    }

    public function previewBarcode(int $id): void
    {
        $this->previewId = $this->previewId === $id ? null : $id;
    }

    public function printLabel(int $id): void
    {
        $product = Product::findOrFail($id);

        if (empty($product->barcode)) {
            session()->flash('error', 'Produk belum memiliki barcode. Isi barcode terlebih dahulu.');

            return;
        }

        $this->redirectRoute('pdf.product-label', ['id' => $product->id, 'copies' => $this->labelCopies], navigate: false);
    }

    public function render()
    {
        $products = Product::with(['category', 'baseUnit'])
            ->when($this->search, function ($query): void {
                $query->where(function ($q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%")
                        ->orWhere('barcode', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.product-list', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('name')->get(['id', 'name']),
            'units' => ProductUnit::orderBy('name')->get(['id', 'name', 'symbol']),
        ]);
    }
}
