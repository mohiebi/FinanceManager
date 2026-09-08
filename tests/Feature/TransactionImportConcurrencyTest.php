<?php

use App\Actions\Transactions\ImportTransactions;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('concurrent confirmation requests serialize on the preview claim and return the same receipt', function () {
    $database = tempnam(sys_get_temp_dir(), 'cashpilot-import-');
    $defaultConnection = DB::getDefaultConnection();
    $connection = [...config('database.connections.sqlite'), 'url' => null, 'database' => $database, 'busy_timeout' => 10000];
    config(['database.connections.import_concurrency' => $connection]);
    DB::setDefaultConnection('import_concurrency');
    $processes = [];

    try {
        Artisan::call('migrate', ['--database' => 'import_concurrency', '--force' => true, '--no-interaction' => true]);
        $user = User::factory()->create();
        $preview = app(ImportTransactions::class)->preview($user, UploadedFile::fake()->createWithContent('import.csv', implode("\n", [
            'occurred_at,type,category,amount,currency,title,description',
            '2026-06-15,cost,Concurrent category,100,toman,Concurrent import,',
        ])));

        $worker = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $settings = json_decode(base64_decode($argv[1]), true);
        config(['database.default' => 'import_concurrency', 'database.connections.import_concurrency' => $settings['connection']]);
        $marker = $settings['connection']['database'];
        $number = $settings['number'];
        Illuminate\Support\Facades\DB::connection()->beforeExecuting(function (string $sql) use ($marker, $number): void {
            if (str_starts_with($sql, 'update "transaction_imports"')) {
                touch($marker.'.attempt-'.$number);
            }
        });
        $saver = new class($marker, $number) extends App\Actions\Transactions\SaveTransaction {
            public function __construct(private string $marker, private int $number) {}
            public function handle(App\Models\User $user, array $data, ?App\Models\Transaction $transaction = null): App\Models\Transaction {
                touch($this->marker.'.saving-'.$this->number);
                $deadline = microtime(true) + 10;
                while ($this->number === 1 && ! is_file($this->marker.'.release')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Timed out waiting to release the first confirmation.');
                    }
                    usleep(10000);
                    clearstatcache();
                }
                return parent::handle($user, $data, $transaction);
            }
        };
        $request = Illuminate\Http\Request::create('/transactions/imports', 'POST', ['token' => $settings['token']]);
        $request->setUserResolver(fn () => App\Models\User::findOrFail($settings['user_id']));
        $response = $app->make(App\Http\Controllers\TransactionImportController::class)->store(
            $request, $app->make(App\Actions\Transactions\ImportTransactions::class), $saver,
        );
        echo $response->getContent();
        PHP;

        $waitForMarker = function (string $path, Process $process): void {
            $deadline = microtime(true) + 10;

            while (! is_file($path) && $process->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
                clearstatcache();
            }

            expect(is_file($path))->toBeTrue($process->getErrorOutput().$process->getOutput());
        };

        foreach ([1, 2] as $number) {
            $process = new Process([PHP_BINARY, '-r', $worker, base64_encode(json_encode([
                'connection' => $connection, 'number' => $number, 'token' => $preview['token'], 'user_id' => $user->id,
            ], JSON_THROW_ON_ERROR))], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key')]);
            $process->setTimeout(15);
            $processes[] = $process;
            $process->start();
            $waitForMarker($database.($number === 1 ? '.saving-1' : '.attempt-2'), $process);
        }

        expect($processes[1]->isRunning())->toBeTrue()
            ->and(is_file($database.'.saving-2'))->toBeFalse();
        touch($database.'.release');

        foreach ($processes as $process) {
            $process->wait();
            expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput())
                ->and(json_decode($process->getOutput(), true))->toBe([
                    'imported' => 1, 'skipped' => 0, 'skipped_duplicates' => 0, 'skipped_invalid' => 0,
                ]);
        }

        expect(is_file($database.'.saving-2'))->toBeFalse()
            ->and($user->transactions()->count())->toBe(1)
            ->and(DB::table('categories')->where('user_id', $user->id)->where('name', 'Concurrent category')->count())->toBe(1);
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }

        DB::purge('import_concurrency');
        DB::setDefaultConnection($defaultConnection);

        foreach (['', '-journal', '-wal', '-shm', '.attempt-1', '.attempt-2', '.saving-1', '.saving-2', '.release'] as $suffix) {
            if (is_file($database.$suffix)) {
                unlink($database.$suffix);
            }
        }
    }
});
