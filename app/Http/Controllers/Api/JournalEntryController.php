<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JournalEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = JournalEntry::with(['lines', 'creator']);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('is_posted')) {
            $query->where('is_posted', filter_var($request->input('is_posted'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->input('reference_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('journal_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('journal_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return JournalEntryResource::collection($entries);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'journal_number' => 'required|string|unique:journal_entries,journal_number',
            'journal_date' => 'required|date',
            'type' => 'required|in:general,sales,purchase,adjustment,closing',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'is_posted' => 'boolean',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.type' => 'required|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string',
        ]);

        $linesData = collect($validated['lines']);
        $totalDebit = $linesData->where('type', 'debit')->sum('amount');
        $totalCredit = $linesData->where('type', 'credit')->sum('amount');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'message' => 'Journal entry is not balanced. Total debit must equal total credit.',
                'errors' => ['lines' => ['Total debit ('.number_format($totalDebit, 2).') does not equal total credit ('.number_format($totalCredit, 2).').']],
            ], 422);
        }

        $entry = JournalEntry::create([
            'journal_number' => $validated['journal_number'],
            'journal_date' => $validated['journal_date'],
            'type' => $validated['type'],
            'reference_type' => $validated['reference_type'] ?? null,
            'reference_id' => $validated['reference_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => auth()->id(),
            'is_posted' => $validated['is_posted'] ?? false,
        ]);

        foreach ($validated['lines'] as $line) {
            $entry->lines()->create($line);
        }

        return (new JournalEntryResource($entry->load('lines')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        $journalEntry->load(['lines', 'creator']);

        return new JournalEntryResource($journalEntry);
    }

    public function update(Request $request, JournalEntry $journalEntry): JournalEntryResource
    {
        if ($journalEntry->is_posted) {
            return response()->json([
                'message' => 'Cannot update a posted journal entry.',
            ], 422);
        }

        $validated = $request->validate([
            'journal_number' => 'required|string|unique:journal_entries,journal_number,'.$journalEntry->id,
            'journal_date' => 'required|date',
            'type' => 'required|in:general,sales,purchase,adjustment,closing',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'is_posted' => 'boolean',
            'lines' => 'sometimes|array|min:2',
            'lines.*.account_id' => 'required_with:lines|exists:accounts,id',
            'lines.*.type' => 'required_with:lines|in:debit,credit',
            'lines.*.amount' => 'required_with:lines|numeric|min:0.01',
            'lines.*.description' => 'nullable|string',
        ]);

        $journalEntry->update([
            'journal_number' => $validated['journal_number'],
            'journal_date' => $validated['journal_date'],
            'type' => $validated['type'],
            'reference_type' => $validated['reference_type'] ?? null,
            'reference_id' => $validated['reference_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_posted' => $validated['is_posted'] ?? $journalEntry->is_posted,
        ]);

        if (isset($validated['lines'])) {
            $linesData = collect($validated['lines']);
            $totalDebit = $linesData->where('type', 'debit')->sum('amount');
            $totalCredit = $linesData->where('type', 'credit')->sum('amount');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                return response()->json([
                    'message' => 'Journal entry is not balanced.',
                    'errors' => ['lines' => ['Total debit must equal total credit.']],
                ], 422);
            }

            $journalEntry->lines()->delete();
            foreach ($validated['lines'] as $line) {
                $journalEntry->lines()->create($line);
            }
        }

        return new JournalEntryResource($journalEntry->fresh()->load('lines'));
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        if ($journalEntry->is_posted) {
            return response()->json([
                'message' => 'Cannot delete a posted journal entry.',
            ], 422);
        }

        $journalEntry->delete();

        return response()->json(null, 204);
    }

    public function post(JournalEntry $journalEntry): JournalEntryResource
    {
        if ($journalEntry->is_posted) {
            return response()->json([
                'message' => 'Journal entry is already posted.',
            ], 422);
        }

        if (! $journalEntry->isBalanced()) {
            return response()->json([
                'message' => 'Cannot post an unbalanced journal entry.',
            ], 422);
        }

        $journalEntry->update(['is_posted' => true]);

        return new JournalEntryResource($journalEntry->fresh()->load('lines'));
    }
}
