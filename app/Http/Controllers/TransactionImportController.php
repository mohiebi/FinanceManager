<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ImportTransactions;
use App\Actions\Transactions\SaveTransaction;
use App\Models\TransactionImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionImportController extends Controller
{
    public function template(): StreamedResponse
    {
        $rows = [
            ['occurred_at', 'type', 'category', 'amount', 'currency', 'title', 'description'],
            ['2026-06-15', 'cost', 'Food', '125000', 'toman', 'Lunch', 'Optional note'],
            ['1403/03/27', "\u{0648}\u{0627}\u{0631}\u{06CC}\u{0632}", "\u{062D}\u{0642}\u{0648}\u{0642}", '85000000', "\u{0631}\u{06CC}\u{0627}\u{0644}", "\u{062D}\u{0642}\u{0648}\u{0642} \u{0645}\u{0627}\u{0647}\u{0627}\u{0646}\u{0647}", "\u{0646}\u{0645}\u{0648}\u{0646}\u{0647} \u{0641}\u{0627}\u{0631}\u{0633}\u{06CC}"],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'transaction-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(Request $request, ImportTransactions $importTransactions): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $result = $importTransactions->preview($request->user(), $validated['file']);
        $transactionImport = TransactionImport::query()->create([
            'user_id' => $request->user()->id,
            'rows' => $result['importable'],
            'summary' => $result['preview']['summary'],
        ]);

        return back()->with('transaction_import_preview', [
            'id' => $transactionImport->id,
            ...$result['preview'],
        ]);
    }

    public function store(
        Request $request,
        ImportTransactions $importTransactions,
        SaveTransaction $saveTransaction,
    ): RedirectResponse {
        $validated = $request->validate([
            'preview_id' => ['required', 'string', 'size:26'],
        ]);

        $result = DB::transaction(function () use ($request, $validated, $importTransactions, $saveTransaction): array {
            $transactionImport = TransactionImport::query()
                ->whereKey($validated['preview_id'])
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (is_array($transactionImport->result)) {
                return $transactionImport->result;
            }

            $rows = is_array($transactionImport->rows) ? $transactionImport->rows : [];
            $summary = is_array($transactionImport->summary) ? $transactionImport->summary : [];
            $importResult = $importTransactions->import($request->user(), $rows, $saveTransaction);
            $result = [
                'imported' => $importResult['imported'],
                'skipped' => max(0, (int) ($summary['total'] ?? count($rows)) - $importResult['imported']),
                'skipped_duplicates' => (int) ($summary['duplicate'] ?? 0) + $importResult['skipped_duplicates'],
            ];

            $transactionImport->update([
                'rows' => [],
                'result' => $result,
                'consumed_at' => now(),
            ]);

            return $result;
        });

        return redirect()
            ->route('transactions.index')
            ->with('transaction_import_result', $result);
    }
}
