<?php

namespace App\Actions\Transactions;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use App\Support\Numerals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;
use Throwable;

class ImportTransactions
{
    private const MAX_ROWS = 500;

    private const HEADERS = [
        'occurred_at',
        'type',
        'category',
        'amount',
        'currency',
        'title',
        'description',
    ];

    /**
     * @return array{token: string, rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function preview(User $user, UploadedFile $file): array
    {
        $records = $this->readCsv($file);
        $categories = $this->categoriesFor($user);
        $previewRows = [];
        $importableRows = [];
        $seenImportKeys = [];

        foreach ($records as $record) {
            $previewRow = $this->normalizeRow($record, $categories);
            $data = $previewRow['data'];

            if ($previewRow['status'] === 'valid' && is_array($data)) {
                $duplicateKey = $this->duplicateKey($data);

                if (isset($seenImportKeys[$duplicateKey]) || $this->isDuplicate($user, $data)) {
                    $previewRow['status'] = 'duplicate';
                    $previewRow['warnings'][] = 'This row looks like an existing transaction and will be skipped.';
                } else {
                    $seenImportKeys[$duplicateKey] = true;
                    $importableRows[] = $this->importData($data);
                }
            }

            $previewRows[] = $previewRow;
        }

        $summary = $this->summary($previewRows);

        // The cast encrypts `rows` under the user's own key at save time, so the
        // rows the user approved never rest in readable form.
        $import = TransactionImport::query()->create([
            'user_id' => $user->id,
            'rows' => $importableRows,
            'summary' => $summary,
            'expires_at' => now()->addDay(),
        ]);

        return ['token' => $import->id, 'rows' => $previewRows, 'summary' => $summary];
    }

    /**
     * The claim, imported rows, categories and receipt commit together. A second
     * request waits on the conditional update and then reads the committed receipt.
     * Rolling back also releases the claim so the same preview can be retried.
     *
     * @return array{imported: int, skipped: int, skipped_duplicates: int, skipped_invalid: int}
     */
    public function confirm(User $user, string $token, SaveTransaction $saveTransaction): array
    {
        return DB::transaction(function () use ($user, $token, $saveTransaction): array {
            /** @var callable(): Builder<TransactionImport> $pending */
            $pending = fn (): Builder => TransactionImport::query()
                ->whereKey($token)
                ->where('user_id', $user->id)
                ->where('expires_at', '>', now());

            $claimed = $pending()->where('claimed', false)->update(['claimed' => true]);
            $preview = $pending()->lockForUpdate()->first();

            if (! $preview instanceof TransactionImport) {
                throw ValidationException::withMessages(['token' => __('finance.import.expired')]);
            }

            if ($preview->result !== null) {
                return $preview->result;
            }

            if ($claimed !== 1) {
                throw ValidationException::withMessages(['token' => __('finance.import.failed')]);
            }

            $result = $this->import($user, $preview->rows ?? [], $saveTransaction);
            $result['skipped'] = $preview->summary['total'] - $result['imported'];
            $result['skipped_duplicates'] += $preview->summary['duplicate'];
            $result['skipped_invalid'] = $preview->summary['invalid'];

            $preview->update(['rows' => null, 'result' => $result]);

            return $result;
        }, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, skipped_duplicates: int}
     */
    private function import(User $user, array $rows, SaveTransaction $saveTransaction): array
    {
        $imported = 0;
        $skippedDuplicates = 0;

        foreach ($rows as $row) {
            if (! $this->isImportableRow($row)) {
                continue;
            }

            if ($this->isDuplicate($user, $row)) {
                $skippedDuplicates++;

                continue;
            }

            $row = $this->resolveImportCategory($user, $row);

            $saveTransaction->handle($user, $row);
            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => count($rows) - $imported,
            'skipped_duplicates' => $skippedDuplicates,
        ];
    }

    /**
     * @return array<int, array{row_number: int, values: array<string, string>}>
     */
    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [[
                'row_number' => 1,
                'values' => ['_error' => 'The uploaded file could not be read.'],
            ]];
        }

        $headers = null;
        $records = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankCsvRow($row)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);

                if ($headers !== self::HEADERS) {
                    fclose($handle);

                    return [[
                        'row_number' => $rowNumber,
                        'values' => ['_error' => 'CSV header must be: '.implode(',', self::HEADERS)],
                    ]];
                }

                continue;
            }

            if (count($records) >= self::MAX_ROWS) {
                break;
            }

            $values = [];

            foreach (self::HEADERS as $index => $header) {
                $values[$header] = trim((string) ($row[$index] ?? ''));
            }

            $records[] = [
                'row_number' => $rowNumber,
                'values' => $values,
            ];
        }

        fclose($handle);

        if ($headers === null) {
            return [[
                'row_number' => 1,
                'values' => ['_error' => 'The uploaded CSV is empty.'],
            ]];
        }

        return $records;
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(
            fn (?string $header): string => Str::lower(trim($this->stripBom((string) $header))),
            $headers,
        );
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{row_number: int, values: array<string, string>}  $record
     * @param  array<string, array<string, Category>>  $categories
     * @return array{row_number: int, status: string, original: array<string, string>, data: array<string, mixed>|null, errors: array<int, string>, warnings: array<int, string>}
     */
    private function normalizeRow(array $record, array $categories): array
    {
        $values = $record['values'];
        $errors = [];
        $warnings = [];

        if (isset($values['_error'])) {
            return [
                'row_number' => $record['row_number'],
                'status' => 'invalid',
                'original' => $values,
                'data' => null,
                'errors' => [$values['_error']],
                'warnings' => [],
            ];
        }

        $type = $this->normalizeType($values['type']);
        $date = $this->normalizeDate($values['occurred_at']);
        $amount = $this->normalizeAmount($values['amount']);
        $currency = $this->normalizeCurrency($values['currency']);
        $title = trim($values['title']);
        $description = trim($values['description']);

        if (! $type instanceof TransactionType) {
            $errors[] = 'Type must be cost/income or a supported Persian equivalent.';
        }

        if ($date === null) {
            $errors[] = 'Date must be a valid Gregorian or Jalali date.';
        }

        if ($amount === null) {
            $errors[] = 'Amount must be a positive number.';
        }

        if ($currency === null) {
            $errors[] = 'Currency must be toman, usd, eur, or rial.';
        }

        if ($title === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($title) > 255) {
            $errors[] = 'Title may not be greater than 255 characters.';
        }

        if (mb_strlen($description) > 5000) {
            $errors[] = 'Description may not be greater than 5000 characters.';
        }

        $categoryName = trim($values['category']);
        $category = $type instanceof TransactionType
            ? $this->normalizeCategory($values['category'], $type, $categories)
            : null;

        if ($categoryName === '') {
            $errors[] = 'Category is required.';
        } elseif (! $category instanceof Category) {
            $warnings[] = 'A custom category will be created for this user.';
        }

        if ($currency === 'rial') {
            $currency = Currency::Toman->value;

            if ($amount !== null) {
                $amount = $amount / 10;
            }

            $warnings[] = 'Rial amount was converted to toman.';
        }

        if ($errors !== []) {
            return [
                'row_number' => $record['row_number'],
                'status' => 'invalid',
                'original' => $values,
                'data' => null,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $data = [
            'type' => $type->value,
            'category_id' => $category?->id,
            'category_name' => $category instanceof Category ? null : $categoryName,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'currency' => $currency,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'occurred_at' => $date,
        ];

        return [
            'row_number' => $record['row_number'],
            'status' => 'valid',
            'original' => $values,
            'data' => [
                ...$data,
                'category' => $category?->name ?? $categoryName,
                'category_is_new' => ! $category instanceof Category,
            ],
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    private function normalizeType(string $value): ?TransactionType
    {
        $normalized = $this->normalizeText($value);

        if (in_array($normalized, array_map($this->normalizeText(...), $this->costAliases()), true)) {
            return TransactionType::Cost;
        }

        if (in_array($normalized, array_map($this->normalizeText(...), $this->incomeAliases()), true)) {
            return TransactionType::Income;
        }

        return TransactionType::tryFrom($normalized);
    }

    private function normalizeDate(string $value): ?string
    {
        $normalized = str_replace(['/', '.', "\u{066B}"], '-', $this->normalizeDigits($value));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        if (! preg_match('/^(\d{2,4})-(\d{1,2})-(\d{1,2})$/', $normalized, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        try {
            if ($year < 1700) {
                return Jalalian::fromFormat('Y-m-d', $date)->toCarbon()->toDateString();
            }

            $carbon = Carbon::createFromFormat('Y-m-d', $date);

            if ($carbon === false || $carbon->format('Y-m-d') !== $date) {
                return null;
            }

            return $carbon->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeAmount(string $value): ?float
    {
        $normalized = $this->normalizeDigits($value);
        $normalized = str_replace([',', "\u{066C}", ' ', "\u{00A0}", '_'], '', $normalized);
        $normalized = str_replace("\u{066B}", '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        $amount = (float) $normalized;

        return $amount > 0 ? $amount : null;
    }

    private function normalizeCurrency(string $value): ?string
    {
        $normalized = $this->normalizeText($value);

        $aliases = [
            Currency::Toman->value => ['toman', 'tomans', "\u{062A}\u{0648}\u{0645}\u{0627}\u{0646}", "\u{062A}\u{0648}\u{0645}\u{0646}"],
            Currency::Usd->value => ['usd', 'dollar', 'dollars', "\u{062F}\u{0644}\u{0627}\u{0631}", "\u{062F}\u{0644}\u{0627}\u{0631} \u{0622}\u{0645}\u{0631}\u{06CC}\u{06A9}\u{0627}", "\u{062F}\u{0644}\u{0627}\u{0631} \u{0627}\u{0645}\u{0631}\u{064A}\u{06A9}\u{0627}"],
            Currency::Eur->value => ['eur', 'euro', 'euros', "\u{06CC}\u{0648}\u{0631}\u{0648}", "\u{064A}\u{0648}\u{0631}\u{0648}"],
            'rial' => ['rial', 'rials', 'irr', "\u{0631}\u{06CC}\u{0627}\u{0644}", "\u{0631}\u{064A}\u{0627}\u{0644}"],
        ];

        foreach ($aliases as $currency => $values) {
            if (in_array($normalized, array_map($this->normalizeText(...), $values), true)) {
                return $currency;
            }
        }

        return Currency::tryFrom($normalized)?->value;
    }

    /**
     * @param  array<string, array<string, Category>>  $categories
     */
    private function normalizeCategory(string $value, TransactionType $type, array $categories): ?Category
    {
        $normalized = $this->normalizeText($value);
        $typeCategories = $categories[$type->value] ?? [];
        $slug = $this->categoryAliasSlug($normalized, $type);

        if ($slug !== null && isset($typeCategories[$slug])) {
            return $typeCategories[$slug];
        }

        return $typeCategories[$normalized] ?? null;
    }

    private function categoryAliasSlug(string $value, TransactionType $type): ?string
    {
        foreach ($this->categoryAliases()[$type->value] as $slug => $aliases) {
            if (in_array($value, array_map($this->normalizeText(...), $aliases), true)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function costAliases(): array
    {
        return [
            'cost',
            'costs',
            'expense',
            'debit',
            'withdraw',
            'withdrawal',
            'payment',
            'purchase',
            "\u{0647}\u{0632}\u{06CC}\u{0646}\u{0647}",
            "\u{0647}\u{0632}\u{064A}\u{0646}\u{0647}",
            "\u{062E}\u{0631}\u{062C}",
            "\u{0628}\u{0631}\u{062F}\u{0627}\u{0634}\u{062A}",
            "\u{062E}\u{0631}\u{0648}\u{062C}\u{06CC}",
            "\u{062E}\u{0631}\u{0648}\u{062C}\u{064A}",
            "\u{0628}\u{062F}\u{0647}\u{06A9}\u{0627}\u{0631}",
            "\u{067E}\u{0631}\u{062F}\u{0627}\u{062E}\u{062A}",
            "\u{062E}\u{0631}\u{06CC}\u{062F}",
            "\u{062E}\u{0631}\u{064A}\u{062F}",
            "\u{06A9}\u{0633}\u{0631}",
        ];
    }

    /**
     * @return array<int, string>
     */
    private function incomeAliases(): array
    {
        return [
            'income',
            'incomes',
            'credit',
            'deposit',
            'incoming',
            "\u{062F}\u{0631}\u{0622}\u{0645}\u{062F}",
            "\u{062F}\u{0631}\u{0627}\u{0645}\u{062F}",
            "\u{0648}\u{0627}\u{0631}\u{06CC}\u{0632}",
            "\u{0648}\u{0627}\u{0631}\u{064A}\u{0632}",
            "\u{0648}\u{0631}\u{0648}\u{062F}\u{06CC}",
            "\u{0648}\u{0631}\u{0648}\u{062F}\u{064A}",
            "\u{0628}\u{0633}\u{062A}\u{0627}\u{0646}\u{06A9}\u{0627}\u{0631}",
            "\u{062F}\u{0631}\u{06CC}\u{0627}\u{0641}\u{062A}\u{06CC}",
            "\u{062F}\u{0631}\u{064A}\u{0627}\u{0641}\u{062A}\u{064A}",
        ];
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function categoryAliases(): array
    {
        return [
            TransactionType::Cost->value => [
                'food' => ['food', "\u{063A}\u{0630}\u{0627}", "\u{062E}\u{0648}\u{0631}\u{0627}\u{06A9}", "\u{0631}\u{0633}\u{062A}\u{0648}\u{0631}\u{0627}\u{0646}", "\u{0633}\u{0648}\u{067E}\u{0631}\u{0645}\u{0627}\u{0631}\u{06A9}\u{062A}"],
                'transport' => ['transport', "\u{062D}\u{0645}\u{0644} \u{0648} \u{0646}\u{0642}\u{0644}", "\u{062D}\u{0645}\u{0644} \u{0648}\u{0646}\u{0642}\u{0644}", "\u{062D}\u{0645}\u{0644}\u{0648}\u{0646}\u{0642}\u{0644}", "\u{062A}\u{0627}\u{06A9}\u{0633}\u{06CC}", "\u{0627}\u{0633}\u{0646}\u{067E}"],
                'housing' => ['housing', "\u{0645}\u{0633}\u{06A9}\u{0646}", "\u{0627}\u{062C}\u{0627}\u{0631}\u{0647}", "\u{062E}\u{0627}\u{0646}\u{0647}"],
                'health' => ['health', "\u{0633}\u{0644}\u{0627}\u{0645}\u{062A}", "\u{062F}\u{0631}\u{0645}\u{0627}\u{0646}", "\u{067E}\u{0632}\u{0634}\u{06A9}\u{06CC}"],
                'shopping' => ['shopping', "\u{062E}\u{0631}\u{06CC}\u{062F}", "\u{062E}\u{0631}\u{064A}\u{062F}"],
                'bills' => ['bills', "\u{0642}\u{0628}\u{0636}", "\u{0642}\u{0628}\u{0648}\u{0636}", "\u{0622}\u{0628}", "\u{0628}\u{0631}\u{0642}", "\u{06AF}\u{0627}\u{0632}", "\u{0627}\u{06CC}\u{0646}\u{062A}\u{0631}\u{0646}\u{062A}"],
                'investment' => ['investment', "\u{0633}\u{0631}\u{0645}\u{0627}\u{06CC}\u{0647} \u{06AF}\u{0630}\u{0627}\u{0631}\u{06CC}"],
                'other' => ['other', "\u{0633}\u{0627}\u{06CC}\u{0631}", "\u{0633}\u{0627}\u{064A}\u{0631}", "\u{0645}\u{062A}\u{0641}\u{0631}\u{0642}\u{0647}"],
            ],
            // Nothing maps to "investment" here: buying an asset is a cost, and the
            // interest keywords that used to live on this line (سود, سود سپرده) are
            // a recurring payout — ordinary income, not an investment. They fall
            // through unmatched rather than being filed under a guess.
            TransactionType::Income->value => [
                'salary' => ['salary', "\u{062D}\u{0642}\u{0648}\u{0642}", "\u{062F}\u{0633}\u{062A}\u{0645}\u{0632}\u{062F}", "\u{06A9}\u{0627}\u{0631}\u{0645}\u{0632}\u{062F}"],
                'freelance' => ['freelance', "\u{0641}\u{0631}\u{06CC}\u{0644}\u{0646}\u{0633}", "\u{0641}\u{0631}\u{064A}\u{0644}\u{0646}\u{0633}", "\u{067E}\u{0631}\u{0648}\u{0698}\u{0647}"],
                'gift' => ['gift', "\u{0647}\u{062F}\u{06CC}\u{0647}", "\u{0647}\u{062F}\u{064A}\u{0647}"],
                'other' => ['other', "\u{0633}\u{0627}\u{06CC}\u{0631}", "\u{0633}\u{0627}\u{064A}\u{0631}", "\u{0645}\u{062A}\u{0641}\u{0631}\u{0642}\u{0647}"],
            ],
        ];
    }

    /**
     * @return array<string, array<string, Category>>
     */
    private function categoriesFor(User $user): array
    {
        $categories = [
            TransactionType::Cost->value => [],
            TransactionType::Income->value => [],
        ];

        Category::query()
            ->availableFor($user)
            ->get()
            ->each(function (Category $category) use (&$categories): void {
                $type = $category->type->value;

                foreach ([$category->slug, $category->name] as $value) {
                    $categories[$type][$this->normalizeText((string) $value)] = $category;
                }
            });

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function resolveImportCategory(User $user, array $row): array
    {
        if (isset($row['category_id']) && $row['category_id'] !== null) {
            unset($row['category_name']);

            return $row;
        }

        $type = TransactionType::from((string) $row['type']);
        $categoryName = trim((string) $row['category_name']);
        $category = Category::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type->value,
                'slug' => $this->categorySlug($categoryName),
            ],
            [
                'name' => $categoryName,
                'is_default' => false,
            ],
        );

        $row['category_id'] = $category->id;
        unset($row['category_name']);

        return $row;
    }

    private function categorySlug(string $value): string
    {
        $slug = Str::slug($this->normalizeText($value));

        if ($slug !== '') {
            return $slug;
        }

        return 'category-'.substr(sha1($this->normalizeText($value)), 0, 16);
    }

    private function normalizeDigits(string $value): string
    {
        return Numerals::toLatin($value);
    }

    private function normalizeText(string $value): string
    {
        $normalized = $this->normalizeDigits($value);
        $normalized = str_replace(["\u{064A}", "\u{0643}", "\u{200C}", '-', '_'], ["\u{06CC}", "\u{06A9}", ' ', ' ', ' '], $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return Str::lower(trim($normalized));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * Narrow to candidates in SQL, then compare the encrypted fields in PHP.
     *
     * `amount` and `title` are encrypted with a fresh IV per write, so the same
     * plaintext produces different ciphertext every time — a SQL equality check
     * against them would never match and every import would look duplicate-free.
     * The unencrypted columns cut the candidate set down to a handful first.
     */
    private function isDuplicate(User $user, array $data): bool
    {
        $amount = $this->amountKey($data['amount']);
        $title = (string) $data['title'];

        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereDate('occurred_at', (string) $data['occurred_at'])
            ->where('type', (string) $data['type'])
            ->where('currency', (string) $data['currency'])
            // user_id is selected because the encryption cast resolves the owning
            // key from it; without it the cast falls back to the authenticated
            // user, which is wrong the moment an import runs from a queue.
            ->get(['id', 'user_id', 'amount', 'title'])
            ->contains(fn (Transaction $transaction): bool => $this->amountKey($transaction->amount) === $amount
                && (string) $transaction->title === $title);
    }

    /**
     * Render an amount at a fixed scale so two amounts can be compared in PHP.
     *
     * The old SQL check leaned on the database coercing "1250" and "1250.00" to
     * the same decimal; a raw string comparison would not.
     */
    private function amountKey(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function duplicateKey(array $data): string
    {
        return implode('|', [
            $data['occurred_at'],
            $data['type'],
            $data['amount'],
            $data['currency'],
            $data['title'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function importData(array $data): array
    {
        return [
            'type' => $data['type'],
            'category_id' => $data['category_id'],
            'category_name' => $data['category_name'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'title' => $data['title'],
            'description' => $data['description'],
            'occurred_at' => $data['occurred_at'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, valid: int, invalid: int, duplicate: int, importable: int}
     */
    private function summary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'valid' => 0,
            'invalid' => 0,
            'duplicate' => 0,
            'importable' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];

            if ($status === 'valid') {
                $summary['valid']++;
                $summary['importable']++;
            } elseif ($status === 'duplicate') {
                $summary['duplicate']++;
            } else {
                $summary['invalid']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isImportableRow(array $row): bool
    {
        return isset(
            $row['type'],
            $row['amount'],
            $row['currency'],
            $row['title'],
            $row['occurred_at'],
        ) && (
            (isset($row['category_id']) && $row['category_id'] !== null)
            || (isset($row['category_name']) && trim((string) $row['category_name']) !== '')
        );
    }
}
