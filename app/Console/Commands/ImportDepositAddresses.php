<?php

namespace App\Console\Commands;

use App\Enums\DepositAddressStatus;
use App\Models\DepositAddress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use Throwable;

#[Signature('billing:import-deposit-addresses {file : CSV containing derivation_index, address} {--dry-run : Validate without importing}')]
#[Description('Import public, offline-generated single-use billing addresses')]
class ImportDepositAddresses extends Command
{
    /**
     * @var array<int, string>
     *
     * No network column. One EVM key controls the same address on every chain,
     * so a pooled address belongs to no network until a payment claims one —
     * and a CSV that named a network produced two rows sharing one derivation
     * index, which the schema now refuses outright.
     */
    private const HEADERS = ['derivation_index', 'address'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The CSV file does not exist or is not readable.');

            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($path);
            $this->assertDatabaseHasNoConflicts($rows);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Validated '.count($rows).' deposit address(es); nothing was imported.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                DepositAddress::create([
                    ...$row,
                    'key_version' => (string) config('billing.signer.key_version', 'v1'),
                    'network' => null,
                    'status' => DepositAddressStatus::Available,
                ]);
            }
        });

        $this->info('Imported '.count($rows).' deposit address(es).');

        return self::SUCCESS;
    }

    /** @return array<int, array{derivation_index: int, address: string}> */
    private function readRows(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = $file->fgetcsv();

        if (! is_array($headers)) {
            throw new \RuntimeException('The CSV has no header row.');
        }

        $headers = array_map(
            fn (mixed $header): string => mb_strtolower(trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B")),
            $headers,
        );

        if ($headers !== self::HEADERS) {
            throw new \RuntimeException('CSV headers must be exactly: derivation_index,address. Secret or additional columns are forbidden.');
        }

        $rows = [];
        $seenIndexes = [];
        $seenAddresses = [];

        $line = 1;

        while (! $file->eof()) {
            $values = $file->fgetcsv();
            $line++;

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            if (count($values) !== count(self::HEADERS)) {
                throw new \RuntimeException("CSV row {$line} has the wrong number of columns.");
            }

            $index = filter_var(trim((string) $values[0]), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $address = mb_strtolower(trim((string) $values[1]));

            if ($index === false || preg_match('/^0x[0-9a-f]{40}$/', $address) !== 1) {
                throw new \RuntimeException("CSV row {$line} contains an invalid derivation index or EVM address.");
            }

            if (isset($seenIndexes[$index]) || isset($seenAddresses[$address])) {
                throw new \RuntimeException("CSV row {$line} duplicates an earlier derivation index or address.");
            }

            $seenIndexes[$index] = true;
            $seenAddresses[$address] = true;
            $rows[] = [
                'derivation_index' => $index,
                'address' => $address,
            ];
        }

        if ($rows === []) {
            throw new \RuntimeException('The CSV contains no deposit addresses.');
        }

        return $rows;
    }

    /** @param  array<int, array{derivation_index: int, address: string}>  $rows */
    private function assertDatabaseHasNoConflicts(array $rows): void
    {
        $keyVersion = (string) config('billing.signer.key_version', 'v1');

        foreach ($rows as $row) {
            $exists = DepositAddress::query()
                ->where(fn ($query) => $query
                    ->where('address', $row['address'])
                    ->orWhere(fn ($derivation) => $derivation
                        ->where('key_version', $keyVersion)
                        ->where('derivation_index', $row['derivation_index'])))
                ->exists();

            if ($exists) {
                throw new \RuntimeException("Address {$row['address']} or derivation index {$row['derivation_index']} already exists in the pool.");
            }
        }
    }
}
