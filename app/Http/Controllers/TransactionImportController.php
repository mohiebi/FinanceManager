<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ImportTransactions;
use App\Actions\Transactions\SaveTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionImportController extends Controller
{
    private const SESSION_IMPORT_ROWS = 'transaction_import_rows';

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

        $request->session()->put(self::SESSION_IMPORT_ROWS, $result['importable']);

        return back()->with('transaction_import_preview', $result['preview']);
    }

    public function store(
        Request $request,
        ImportTransactions $importTransactions,
        SaveTransaction $saveTransaction,
    ): RedirectResponse {
        $rows = $request->session()->get(self::SESSION_IMPORT_ROWS, []);

        $result = $importTransactions->import($request->user(), is_array($rows) ? $rows : [], $saveTransaction);

        $request->session()->forget(self::SESSION_IMPORT_ROWS);

        return redirect()
            ->route('transactions.index')
            ->with('transaction_import_result', $result);
    }
}
