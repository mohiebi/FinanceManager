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
        $health = [];

        try {
            $health = $signer->health();
            $this->line('Signer: '.(($health['ok'] ?? false) ? 'reachable' : 'unhealthy').', '.(($health['locked'] ?? true) ? 'locked' : 'unlocked'));
            $failed = $failed || ! ($health['ok'] ?? false) || ($health['locked'] ?? true);
        } catch (\Throwable $exception) {
            $this->error('Signer: '.$exception->getMessage());
            $failed = true;
        }

        $available = DepositAddress::query()->where('status', DepositAddressStatus::Available->value)->count();
        $this->line("Unused addresses: {$available}");
        $failed = $failed || $available < (int) config('billing.deposit_pool.low_address_warning', 25);

        foreach (PaymentNetwork::available() as $network) {
            $rpcHealthy = $this->rpcHasExpectedChain($network);
            $this->line("{$network->label()}: RPC ".($rpcHealthy ? 'ok' : 'failed'));
            $failed = ! $this->reportVault($network, $health) || $failed || ! $rpcHealthy;
        }

        if ($failed) {
            $this->error('Verification failed. Keep BILLING_ENABLED=false until every line above is ok.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Compare the vault this application was told about against the one the
     * signer will actually sweep to, and prove something is deployed there.
     *
     * A Safe address is the same on every chain it was deployed to with the same
     * salt — and identical on every chain it was never deployed to at all. Ether
     * swept to the second kind is gone until somebody deploys the Safe there,
     * which is not a discovery to make with a buyer's money.
     *
     * @param  array<string, mixed>  $health
     */
    private function reportVault(PaymentNetwork $network, array $health): bool
    {
        $configured = mb_strtolower(trim((string) config("billing.settlement.vaults.{$network->value}")));
        $reported = $health['vaults'][$network->value] ?? null;

        if (preg_match('/^0x[0-9a-f]{40}$/', $configured) !== 1) {
            $this->line("{$network->label()}: vault invalid");

            return false;
        }

        if (! is_array($reported) || ($reported['configured'] ?? false) !== true) {
            $this->line("{$network->label()}: the signer has no vault for this network");

            return false;
        }

        if (($reported['unreachable'] ?? false) === true) {
            $this->line("{$network->label()}: the signer could not reach the chain to check its vault");

            return false;
        }

        if (mb_strtolower((string) ($reported['vault'] ?? '')) !== $configured) {
            $this->line("{$network->label()}: vault MISMATCH — this app has {$configured}, the signer will sweep to ".(string) ($reported['vault'] ?? 'nothing'));

            return false;
        }

        if (($reported['vaultHasCode'] ?? false) !== true) {
            $this->line("{$network->label()}: vault {$configured} has no bytecode. If this is a Safe it was never deployed on this chain.");

            return false;
        }

        if (($reported['riskVaultHasCode'] ?? false) !== true) {
            $this->line("{$network->label()}: risk vault ".(string) ($reported['riskVault'] ?? '').' has no bytecode.');

            return false;
        }

        $segregated = ($reported['segregated'] ?? false) === true;
        $this->line("{$network->label()}: vault ok, risk vault ".($segregated ? 'segregated' : 'NOT segregated (flagged and unscreened funds go to the main vault)'));

        return true;
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
