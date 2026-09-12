<?php

namespace App\Livewire;

use App\Models\Account;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class ChartOfAccounts extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'asset';

    public ?int $parent_id = null;

    protected function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:accounts,code,'.($this->editId ?? 'NULL'),
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $account = Account::findOrFail($id);
        $this->editId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->type = $account->type;
        $this->parent_id = $account->parent_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editId) {
            $account = Account::findOrFail($this->editId);
            $account->update([
                'code' => $this->code,
                'name' => $this->name,
                'type' => $this->type,
                'parent_id' => $this->parent_id,
            ]);
            session()->flash('message', 'Akun berhasil diperbarui.');
        } else {
            Account::create([
                'code' => $this->code,
                'name' => $this->name,
                'type' => $this->type,
                'parent_id' => $this->parent_id,
                'is_active' => true,
            ]);
            session()->flash('message', 'Akun berhasil ditambahkan.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $account = Account::findOrFail($id);
        $account->delete();
        session()->flash('message', 'Akun berhasil dihapus.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->code = '';
        $this->name = '';
        $this->type = 'asset';
        $this->parent_id = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        $query = Account::with('parent')
            ->when($this->search, fn ($q) => $q->where('code', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%"))
            ->orderBy('code');

        return view('livewire.chart-of-accounts', [
            'accounts' => $query->paginate(20),
            'parentAccounts' => Account::orderBy('code')->get(),
        ]);
    }
}
