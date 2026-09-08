<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ImportTransactions;
use App\Actions\Transactions\SaveTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function preview(Request $request, ImportTransactions $importTransactions): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        return response()->json($importTransactions->preview($request->user(), $validated['file']));
    }

    public function store(
        Request $request,
        ImportTransactions $importTransactions,
        SaveTransaction $saveTransaction,
    ): JsonResponse {
        $validated = $request->validate(['token' => ['required', 'uuid']]);

        return response()->json($importTransactions->confirm($request->user(), $validated['token'], $saveTransaction));
    }
}
