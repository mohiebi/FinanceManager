<?php

namespace App\Console\Commands;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Models\DepositAddress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use Throwable;

#[Signature('billing:import-deposit-addresses {file : CSV containing network, derivation_index, address} {--dry-run : Validate without importing}')]
#[Description('Import public, offline-generated single-use billing addresses')]
class ImportDepositAddresses extends Command
{
    /** @var array<int, string> */
    private const HEADERS = ['network', 'derivation_index', 'address'];

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
                    'status' => DepositAddressStatus::Available,
                ]);
            }
        });

        $this->info('Imported '.count($rows).' deposit address(es).');

        return self::SUCCESS;
    }

    /** @return array<int, array{network: string, derivation_index: int, address: string}> */
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
            throw new \RuntimeException('CSV headers must be exactly: network,derivation_index,address. Secret or additional columns are forbidden.');
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

            $network = PaymentNetwork::tryFrom(mb_strtolower(trim((string) $values[0])));
            $index = filter_var(trim((string) $values[1]), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $address = mb_strtolower(trim((string) $values[2]));

            if ($network === null || $index === false || preg_match('/^0x[0-9a-f]{40}$/', $address) !== 1) {
                throw new \RuntimeException("CSV row {$line} contains an invalid network, derivation index, or EVM address.");
            }

            $indexKey = $network->value.':'.$index;
            $addressKey = $network->value.':'.$address;

            if (isset($seenIndexes[$indexKey]) || isset($seenAddresses[$addressKey])) {
                throw new \RuntimeException("CSV row {$line} duplicates an earlier network index or address.");
            }

            $seenIndexes[$indexKey] = true;
            $seenAddresses[$addressKey] = true;
            $rows[] = [
                'network' => $network->value,
                'derivation_index' => $index,
                'address' => $address,
            ];
        }

        if ($rows === []) {
            throw new \RuntimeException('The CSV contains no deposit addresses.');
        }

        return $rows;
    }

    /** @param  array<int, array{network: string, derivation_index: int, address: string}>  $rows */
    private function assertDatabaseHasNoConflicts(array $rows): void
    {
        foreach ($rows as $row) {
            $exists = DepositAddress::query()
                ->where('network', $row['network'])
                ->where(fn ($query) => $query
                    ->where('derivation_index', $row['derivation_index'])
                    ->orWhere('address', $row['address']))
                ->exists();

            if ($exists) {
                throw new \RuntimeException("A {$row['network']} address or derivation index already exists in the pool.");
            }
        }
    }
}
