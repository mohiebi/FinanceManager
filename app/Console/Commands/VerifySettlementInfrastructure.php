<?php

namespace App\Console\Commands;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Models\DepositAddress;
use App\Services\Billing\ScreeningRpcPolicy;
use App\Services\Billing\WalletSignerClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('billing:verify-settlement')]
#[Description('Read-only verification of billing RPCs, vaults, signer dependencies, and address pool')]
class VerifySettlementInfrastructure extends Command
{
    public function handle(WalletSignerClient $signer, ScreeningRpcPolicy $screeningRpcPolicy): int
    {
        $failed = false;
        $health = [];

        try {
            $health = $signer->health(deep: true);
            $this->line('Signer: '.(($health['ok'] ?? false) ? 'reachable' : 'unhealthy').', '.(($health['locked'] ?? true) ? 'locked' : 'unlocked'));
            $failed = $failed || ! ($health['ok'] ?? false) || ($health['locked'] ?? true);
        } catch (\Throwable $exception) {
            $this->error('Signer: '.$exception->getMessage());
            $failed = true;
        }

        $available = DepositAddress::query()->where('status', DepositAddressStatus::Available->value)->count();
        $this->line("Unused addresses: {$available}");
        $failed = $failed || $available < (int) config('billing.deposit_pool.low_address_warning', 25);

        $screeningEnabled = (bool) config('billing.screening.enabled', false);
        $this->line('Screening: '.($screeningEnabled ? 'enabled' : 'DISABLED'));
        $failed = $failed || ! $screeningEnabled;

        foreach (PaymentNetwork::available() as $network) {
            $rpcHealthy = $this->rpcHasExpectedChain($network);
            $this->line("{$network->label()}: RPC ".($rpcHealthy ? 'ok' : 'failed'));
            $screeningEndpoints = $screeningRpcPolicy->endpointsFor($network);
            $screeningHealthy = count($screeningEndpoints) >= 2;
            $this->line("{$network->label()}: independent screening quorum ".($screeningHealthy ? 'ok' : 'invalid'));
            $dependenciesHealthy = $this->reportSettlementDependencies($network, $health);
            $failed = ! $this->reportVault($network, $health)
                || $failed
                || ! $rpcHealthy
                || ! $screeningHealthy
                || ! $dependenciesHealthy;
        }

        if ($failed) {
            $this->error('Verification failed. Keep BILLING_ENABLED=false until every line above is ok.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Compare the vault this application was told about against the one the
     * signer will actually sweep to, and say what kind of address it is.
     *
     * A plain account works as a vault on every chain and needs no deployment.
     * A contract does not: a Safe address is the same on every chain it was
     * deployed to with the same salt, and identical on every chain it was never
     * deployed to at all. Ether swept to the second kind is stuck until somebody
     * deploys the Safe there.
     *
     * Which of the two is right is the operator's decision, so the absence of
     * bytecode is reported rather than refused — only a disagreement between
     * this application and the signer is fatal.
     *
     * @param  array<string, mixed>  $health
     */
    private function reportVault(PaymentNetwork $network, array $health): bool
    {
        $configured = mb_strtolower(trim((string) config("billing.settlement.vaults.{$network->value}")));
        $configuredRisk = mb_strtolower(trim((string) config("billing.settlement.risk_vaults.{$network->value}")));
        $reported = $health['vaults'][$network->value] ?? null;

        if (
            preg_match('/^0x[0-9a-f]{40}$/', $configured) !== 1
            || preg_match('/^0x[0-9a-f]{40}$/', $configuredRisk) !== 1
            || $configured === $configuredRisk
        ) {
            $this->line("{$network->label()}: clean and risk vaults must be valid, distinct addresses");

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

        if (mb_strtolower((string) ($reported['riskVault'] ?? '')) !== $configuredRisk) {
            $this->line("{$network->label()}: risk vault MISMATCH — this app has {$configuredRisk}, the signer will sweep to ".(string) ($reported['riskVault'] ?? 'nothing'));

            return false;
        }

        $kind = fn (bool $hasCode): string => $hasCode ? 'contract' : 'plain account';
        $vaultKind = $kind(($reported['vaultHasCode'] ?? false) === true);
        $riskVaultKind = $kind(($reported['riskVaultHasCode'] ?? false) === true);
        $segregated = ($reported['segregated'] ?? false) === true;

        $this->line("{$network->label()}: vault ok ({$vaultKind}), risk vault {$riskVaultKind}, "
            .($segregated ? 'segregated' : 'NOT segregated — flagged and unscreened funds go to the main vault'));

        if (! $segregated) {
            return false;
        }

        if ($vaultKind === 'plain account' || $riskVaultKind === 'plain account') {
            $this->warn('  A vault with no bytecode is a plain account. That is fine and works on every chain — but if you meant to use a Safe, it was never deployed here, and anything swept to it stays stuck until you deploy one.');
        }

        return true;
    }

    /** @param  array<string, mixed>  $health */
    private function reportSettlementDependencies(PaymentNetwork $network, array $health): bool
    {
        $dependencies = $health['dependencies'][$network->value] ?? null;

        if (! is_array($dependencies)) {
            $this->line("{$network->label()}: signer did not return deep dependency verification");

            return false;
        }

        if (($dependencies['ready'] ?? false) !== true) {
            $reason = trim((string) ($dependencies['error'] ?? 'dependency verification failed'));
            $this->line("{$network->label()}: settlement dependencies invalid — {$reason}");

            return false;
        }

        $assets = implode(', ', array_map('strval', (array) ($dependencies['assets'] ?? [])));
        $this->line("{$network->label()}: settlement dependencies ok".($assets === '' ? '' : " ({$assets})"));

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
