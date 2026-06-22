<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Services\AssetPriceService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioExportController extends Controller
{
    public function __invoke(
        Request $request,
        AssetPriceService $priceService,
        CurrencyConverter $currencyConverter,
    ): StreamedResponse {
        $user = $request->user();
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;
        $currencyLabel = strtoupper($selectedCurrency->value);

        $convert = function (float $amount) use ($selectedCurrency, $currencyConverter): float {
            if ($selectedCurrency === Currency::Toman) {
                return round($amount, 0);
            }

            return round($currencyConverter->convert($amount, Currency::Toman, $selectedCurrency), 4);
        };

        $allEntries = $user->investments()
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();

        $grouped = $allEntries->groupBy(fn ($investment) => $investment->asset_type->value);
        $grouped = $grouped->sortByDesc(fn ($entries, $typeValue) => $priceService->valueOf(
            AssetType::from($typeValue),
            (float) $entries->sum('quantity'),
        ));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Portfolio P&L');

        $headers = [
            'Asset',
            'Quantity',
            'Unit',
            "Current Price ({$currencyLabel})",
            "Current Value ({$currencyLabel})",
            "Avg Cost Basis (per unit, {$currencyLabel})",
            "Total Cost ({$currencyLabel})",
            "P&L ({$currencyLabel})",
            'P&L %',
            'P&L Direction',
            'Entries',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $lastCol = 'K';
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1a1a1a');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 2;
        $totalCurrentValue = 0.0;
        $totalCost = 0.0;

        foreach ($grouped as $typeValue => $typeEntries) {
            $type = AssetType::from($typeValue);
            $totalQuantity = (float) $typeEntries->sum('quantity');
            $currentPrice = $priceService->priceFor($type);
            $currentValue = $priceService->valueOf($type, $totalQuantity);

            $entriesWithCostBasis = $typeEntries->filter(fn ($e) => $e->cost_basis !== null);
            $totalCostBasisValue = $entriesWithCostBasis->sum(fn ($e) => (float) $e->cost_basis * (float) $e->quantity);
            $totalCostBasisQuantity = (float) $entriesWithCostBasis->sum('quantity');
            $avgCostBasis = $totalCostBasisQuantity > 0 ? $totalCostBasisValue / $totalCostBasisQuantity : null;
            $assetTotalCost = $avgCostBasis !== null ? $avgCostBasis * $totalQuantity : null;
            $pnl = $assetTotalCost !== null ? $currentValue - $assetTotalCost : null;
            $pnlPercent = ($assetTotalCost !== null && $assetTotalCost > 0)
                ? round(($pnl / $assetTotalCost) * 100, 2)
                : null;

            $totalCurrentValue += $currentValue;
            if ($assetTotalCost !== null) {
                $totalCost += $assetTotalCost;
            }

            $sheet->fromArray([
                $type->label(),
                round($totalQuantity, 8),
                $type->unit(),
                $convert($currentPrice),
                $convert($currentValue),
                $avgCostBasis !== null ? $convert($avgCostBasis) : '',
                $assetTotalCost !== null ? $convert($assetTotalCost) : '',
                $pnl !== null ? $convert(abs($pnl)) : '',
                $pnlPercent !== null ? $pnlPercent . '%' : '',
                $pnl !== null ? ($pnl >= 0 ? 'Profit' : 'Loss') : '',
                $typeEntries->count(),
            ], null, "A{$row}");
            $row++;
        }

        // Summary row
        $sheet->fromArray([
            'TOTAL',
            '',
            '',
            '',
            $convert($totalCurrentValue),
            '',
            $totalCost > 0 ? $convert($totalCost) : '',
            $totalCost > 0 ? $convert(abs($totalCurrentValue - $totalCost)) : '',
            $totalCost > 0 ? round(($totalCurrentValue - $totalCost) / $totalCost * 100, 2) . '%' : '',
            $totalCost > 0 ? ($totalCurrentValue >= $totalCost ? 'Profit' : 'Loss') : '',
            '',
        ], null, "A{$row}");

        $summaryStyle = $sheet->getStyle("A{$row}:{$lastCol}{$row}");
        $summaryStyle->getFont()->setBold(true);
        $summaryStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0d2620');

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'portfolio_pnl_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
