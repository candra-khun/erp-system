<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\JournalEntry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Jurnal Umum'])]
class JournalEntryList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->is_posted) {
            session()->flash('error', 'Jurnal yang sudah dipost tidak dapat dihapus. Buat jurnal penyesuaian (reversal) sebagai gantinya.');

            return;
        }

        $entry->delete();
        session()->flash('message', 'Journal Entry berhasil dihapus.');
    }

    public function render(): View
    {
        $query = JournalEntry::with('lines.account')
            ->when($this->search, fn ($q) => $q->where('journal_number', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'posted', fn ($q) => $q->where('is_posted', true))
            ->when($this->statusFilter === 'draft', fn ($q) => $q->where('is_posted', false))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('journal_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('journal_date', '<=', $this->dateTo))
            ->orderByDesc('journal_date')
            ->orderByDesc('id');

        return view('livewire.journal-entry-list', [
            'entries' => $query->paginate(15),
        ]);
    }
}
