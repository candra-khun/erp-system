<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\WorkShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Jadwal Shift'])]
class WorkShiftList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $shiftId = null;

    public string $name = '';

    public string $start_time = '08:00';

    public string $end_time = '16:00';

    public string $late_tolerance_minutes = '0';

    public string $early_leave_tolerance_minutes = '0';

    public bool $is_active = true;

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'late_tolerance_minutes' => 'required|integer|min:0|max:480',
            'early_leave_tolerance_minutes' => 'required|integer|min:0|max:480',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, WorkShift>
     */
    #[Computed]
    public function shifts()
    {
        return WorkShift::when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('start_time')
            ->paginate(15);
    }

    public function createShift(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function editShift(WorkShift $shift): void
    {
        $this->shiftId = $shift->id;
        $this->name = $shift->name;
        $this->start_time = $shift->start_time->format('H:i');
        $this->end_time = $shift->end_time->format('H:i');
        $this->late_tolerance_minutes = (string) $shift->late_tolerance_minutes;
        $this->early_leave_tolerance_minutes = (string) $shift->early_leave_tolerance_minutes;
        $this->is_active = (bool) $shift->is_active;
        $this->isEdit = true;
        $this->showModal = true;
        $this->errorMessage = '';
    }

    public function saveShift(): void
    {
        $validated = $this->validate();

        try {
            WorkShift::updateOrCreate(
                ['id' => $this->shiftId],
                [
                    'name' => $validated['name'],
                    'start_time' => $validated['start_time'].':00',
                    'end_time' => $validated['end_time'].':00',
                    'late_tolerance_minutes' => (int) $validated['late_tolerance_minutes'],
                    'early_leave_tolerance_minutes' => (int) $validated['early_leave_tolerance_minutes'],
                    'is_active' => (bool) $validated['is_active'],
                ],
            );

            $this->showModal = false;
            $this->resetForm();
            session()->flash('success', 'Shift berhasil disimpan.');
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menyimpan shift: '.$e->getMessage();
        }
    }

    public function deleteShift(WorkShift $shift): void
    {
        try {
            $shift->delete();
            session()->flash('success', 'Shift berhasil dihapus.');
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menghapus shift: '.$e->getMessage();
        }
    }

    private function resetForm(): void
    {
        $this->shiftId = null;
        $this->name = '';
        $this->start_time = '08:00';
        $this->end_time = '16:00';
        $this->late_tolerance_minutes = '0';
        $this->early_leave_tolerance_minutes = '0';
        $this->is_active = true;
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.work-shift-list', [
            'shifts' => $this->shifts,
        ]);
    }
}
