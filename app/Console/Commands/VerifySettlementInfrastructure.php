<?php

namespace App\Console\Commands;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Models\DepositAddress;
use App\Services\Billing\WalletSignerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class VerifySettlementInfrastructure extends Command
{
    protected $signature = 'billing:verify-settlement';

    protected $description = 'Read-only verification of billing RPCs, vaults, signer health, and address pool';

    public function handle(WalletSignerClient $signer): int
    {
        $failed = false;

        try {
            $health = $signer->health();
            $this->line('Signer: '.(($health['ok'] ?? false) ? 'reachable' : 'unhealthy').', '.(($health['locked'] ?? true) ? 'locked' : 'unlocked'));
            $failed = $failed || ! ($health['ok'] ?? false);
        } catch (\Throwable $exception) {
            $this->error('Signer: '.$exception->getMessage());
            $failed = true;
        }

        $available = DepositAddress::query()->where('status', DepositAddressStatus::Available->value)->count();
        $this->line("Unused addresses: {$available}");
        $failed = $failed || $available < (int) config('billing.deposit_pool.low_address_warning', 25);

        foreach (PaymentNetwork::available() as $network) {
            $vault = mb_strtolower(trim((string) config("billing.settlement.vaults.{$network->value}")));
            $vaultValid = preg_match('/^0x[0-9a-f]{40}$/', $vault) === 1;
            $rpcHealthy = $this->rpcHasExpectedChain($network);
            $this->line("{$network->label()}: RPC ".($rpcHealthy ? 'ok' : 'failed').', vault '.($vaultValid ? 'valid' : 'invalid'));
            $failed = $failed || ! $vaultValid || ! $rpcHealthy;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function rpcHasExpectedChain(PaymentNetwork $network): bool
    {
        foreach ($network->rpcUrls() as $url) {
            try {
                $response = Http::timeout(8)->connectTimeout(3)->asJson()->post($url, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'eth_chainId',
                    'params' => [],
                ]);
                $result = $response->json('result');

                if ($response->successful() && is_string($result) && (int) hexdec(mb_substr($result, 2)) === $network->chainId()) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }
}
