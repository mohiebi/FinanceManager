<?php

namespace App\Http\Controllers;

use App\Support\FrontendLocalization;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        $transactions = $user->transactions()
            ->with('category:id,name')
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Group by calendar month
        $grouped = $transactions->groupBy(function ($t) use ($calendar) {
            $date = $t->occurred_at;
            if ($calendar === 'jalali') {
                $j = Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse($date));

                return $j->format('Y/m');
            }

            return \Illuminate\Support\Carbon::parse($date)->format('Y-m');
        });

        // Sort groups chronologically
        $grouped = $grouped->sortKeys();

        foreach ($grouped as $monthKey => $monthTransactions) {
            $sheetName = $this->monthLabel($monthKey, $calendar);

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            // Header row
            $headers = ['Date', 'Title', 'Category', 'Type', 'Amount', 'Currency', 'Description'];
            $sheet->fromArray($headers, null, 'A1');

            // Style header
            $headerStyle = $sheet->getStyle('A1:G1');
            $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1a1a1a');
            $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Data rows
            $row = 2;
            foreach ($monthTransactions->sortBy('occurred_at') as $t) {
                $date = \Illuminate\Support\Carbon::parse($t->occurred_at);
                $displayDate = $calendar === 'jalali'
                    ? Jalalian::fromCarbon($date)->format('Y/m/d')
                    : $date->format('Y-m-d');

                $sheet->fromArray([
                    $displayDate,
                    $t->title,
                    $t->category?->name ?? '',
                    $t->type->value,
                    (float) $t->amount,
                    strtoupper($t->currency->value),
                    $t->description ?? '',
                ], null, "A{$row}");
                $row++;
            }

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('No Data');
            $sheet->setCellValue('A1', 'No transactions found');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'transactions_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function monthLabel(string $monthKey, string $calendar): string
    {
        if ($calendar === 'jalali') {
            // monthKey is "1405/03" format
            [$year, $month] = explode('/', $monthKey);
            $monthNames = ['', 'Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar', 'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand'];

            return ($monthNames[(int) $month] ?? $month) . ' ' . $year;
        }

        // monthKey is "2026-03" format
        return \Illuminate\Support\Carbon::parse($monthKey . '-01')->format('M Y');
    }
}
