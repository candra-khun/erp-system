<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\ProductCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Kategori Produk'])]
class CategoryList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $name = '';

    public string $parent_id = '';

    public string $description = '';

    public string $sort_order = '0';

    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editId', 'name', 'parent_id', 'description', 'sort_order']);
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $category = ProductCategory::findOrFail($id);

        $this->editId = $category->id;
        $this->name = $category->name;
        $this->parent_id = (string) ($category->parent_id ?? '');
        $this->description = (string) $category->description;
        $this->sort_order = (string) ($category->sort_order ?? 0);
        $this->is_active = (bool) $category->is_active;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:100|unique:product_categories,name'.($this->editId ? ",{$this->editId}" : '').',id',
            'parent_id' => 'nullable|exists:product_categories,id|different:editId',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'parent_id' => $this->parent_id !== '' ? (int) $this->parent_id : null,
            'slug' => Str::slug($validated['name']),
            'description' => $this->description !== '' ? $this->description : null,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->editId) {
            $category = ProductCategory::findOrFail($this->editId);
            if ((int) $this->parent_id === $category->id) {
                $this->addError('parent_id', 'Kategori tidak bisa menjadi induk dirinya sendiri.');

                return;
            }
            $category->update($data);
            session()->flash('success', 'Kategori berhasil diperbarui.');
        } else {
            ProductCategory::create($data);
            session()->flash('success', 'Kategori berhasil dibuat.');
        }

        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $category = ProductCategory::findOrFail($id);

        if ($category->children()->exists() || $category->products()->exists()) {
            session()->flash('error', 'Kategori masih memiliki sub-kategori atau produk. Pindahkan terlebih dahulu.');

            return;
        }

        $category->delete();
        session()->flash('success', 'Kategori berhasil dihapus (soft delete).');
    }

    public function render()
    {
        $categories = ProductCategory::with(['parent', 'children'])
            ->withCount('products')
            ->when($this->search, function ($query): void {
                $query->where('name', 'like', "%{$this->search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.category-list', [
            'categories' => $categories,
            'allCategories' => ProductCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
