<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Kalender Hari Libur'])]
class HolidayList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $holidayId = null;

    public string $name = '';

    public string $holidayDate = '';

    public string $type = 'national';

    public bool $isRecurringAnnual = false;

    public ?int $weeklyDayOfWeek = null;

    public string $notes = '';

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'holidayDate' => 'nullable|date',
            'type' => 'required|in:national,religious,company,weekly_off',
            'isRecurringAnnual' => 'boolean',
            'weeklyDayOfWeek' => 'nullable|integer|min:0|max:6',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Holiday>
     */
    #[Computed]
    public function holidays()
    {
        return Holiday::when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('holiday_date')
            ->paginate(15);
    }

    public function createHoliday(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function editHoliday(Holiday $holiday): void
    {
        $this->holidayId = $holiday->id;
        $this->name = $holiday->name;
        $this->holidayDate = $holiday->holiday_date?->format('Y-m-d') ?? '';
        $this->type = $holiday->type->value;
        $this->isRecurringAnnual = (bool) $holiday->is_recurring_annual;
        $this->weeklyDayOfWeek = $holiday->weekly_day_of_week;
        $this->notes = (string) $holiday->notes;
        $this->isEdit = true;
        $this->showModal = true;
        $this->errorMessage = '';
    }

    public function saveHoliday(): void
    {
        $validated = $this->validate();

        if ($validated['type'] === 'weekly_off' && ($validated['weeklyDayOfWeek'] === null || $validated['weeklyDayOfWeek'] === '')) {
            $this->addError('weeklyDayOfWeek', 'Hari libur mingguan wajib memilih hari.');

            return;
        }

        Holiday::updateOrCreate(
            ['id' => $this->holidayId],
            [
                'name' => $validated['name'],
                'holiday_date' => $validated['type'] === 'weekly_off' ? null : $validated['holidayDate'],
                'type' => $validated['type'],
                'is_recurring_annual' => (bool) $validated['isRecurringAnnual'],
                'weekly_day_of_week' => $validated['type'] === 'weekly_off' ? (int) $validated['weeklyDayOfWeek'] : null,
                'notes' => $validated['notes'] ?: null,
            ],
        );

        $this->showModal = false;
        $this->resetForm();
        session()->flash('success', 'Hari libur berhasil disimpan.');
    }

    public function deleteHoliday(Holiday $holiday): void
    {
        $holiday->delete();
        session()->flash('success', 'Hari libur dihapus.');
    }

    private function resetForm(): void
    {
        $this->holidayId = null;
        $this->name = '';
        $this->holidayDate = now()->toDateString();
        $this->type = 'national';
        $this->isRecurringAnnual = false;
        $this->weeklyDayOfWeek = null;
        $this->notes = '';
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.holiday-list', [
            'holidays' => $this->holidays,
            'types' => HolidayType::cases(),
            'dayNames' => [
                0 => 'Minggu',
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
            ],
        ]);
    }
}
