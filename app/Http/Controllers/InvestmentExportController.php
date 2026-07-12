<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Services\AssetPriceService;
use App\Support\CurrencyPreference;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvestmentExportController extends Controller
{
    public function __invoke(
        Request $request,
        AssetPriceService $priceService,
        CurrencyConverter $currencyConverter,
    ): StreamedResponse {
        $user = $request->user();
        $selectedCurrency = CurrencyPreference::resolve($request);
        $currencyLabel = strtoupper($selectedCurrency->value);

        $convert = function (float $amount) use ($selectedCurrency, $currencyConverter): float {
            if ($selectedCurrency === Currency::Toman) {
                return round($amount, 0);
            }

            return round($currencyConverter->convert($amount, Currency::Toman, $selectedCurrency), 4);
        };

        $entries = $user->investments()
            ->with('asset')
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Investment Entries');

        $headers = [
            'Date',
            'Asset',
            'Unit',
            'Quantity',
            'Cost Basis (per unit)',
            'Cost Basis Currency',
            "Current Price ({$currencyLabel})",
            "Current Value ({$currencyLabel})",
            "P&L ({$currencyLabel})",
            'P&L Direction',
            'Note',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $lastCol = 'K';
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1a1a1a');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 2;
        foreach ($entries as $entry) {
            $asset = $entry->asset;

            if (! $asset) {
                continue;
            }

            $currentPrice = $convert($priceService->priceFor($asset));
            $currentValue = $convert($priceService->valueOf($asset, (float) $entry->quantity));

            $pnl = null;
            $pnlDirection = '';
            if ($entry->cost_basis !== null) {
                $costBasisInToman = $entry->cost_basis_currency === Currency::Toman->value
                    ? (float) $entry->cost_basis
                    : $currencyConverter->convert((float) $entry->cost_basis, Currency::tryFrom($entry->cost_basis_currency ?? 'toman') ?? Currency::Toman, Currency::Toman);
                $pnlToman = $priceService->valueOf($asset, (float) $entry->quantity)
                    - ($costBasisInToman * (float) $entry->quantity);
                $pnl = $convert($pnlToman);
                $pnlDirection = $pnlToman >= 0 ? 'Profit' : 'Loss';
            }

            $sheet->fromArray([
                $entry->occurred_at->toDateString(),
                $asset->label(),
                $asset->unit,
                (float) $entry->quantity,
                $entry->cost_basis !== null ? (float) $entry->cost_basis : '',
                $entry->cost_basis_currency !== null ? strtoupper($entry->cost_basis_currency) : '',
                $currentPrice,
                $currentValue,
                $pnl !== null ? abs($pnl) : '',
                $pnlDirection,
                $entry->note ?? '',
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'investments_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
