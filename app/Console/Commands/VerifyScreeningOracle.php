<?php

namespace App\Console\Commands;

use App\Enums\PaymentNetwork;
use App\Enums\ScreeningRisk;
use App\Enums\SettlementAsset;
use App\Services\Billing\OnChainSanctionsOracleScreener;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:verify-screening-oracle {--network=* : Network(s) to verify; defaults to all} {--sanctioned= : Known sanctioned test address} {--clear=0x0000000000000000000000000000000000000000 : Known non-sanctioned test address}')]
#[Description('Verify oracle bytecode, chain identity, and known positive and negative addresses before enabling screening')]
class VerifyScreeningOracle extends Command
{
    public function handle(OnChainSanctionsOracleScreener $screener): int
    {
        $sanctioned = mb_strtolower(trim((string) $this->option('sanctioned')));
        $clear = mb_strtolower(trim((string) $this->option('clear')));

        if (
            preg_match('/^0x[0-9a-f]{40}$/', $sanctioned) !== 1
            || preg_match('/^0x[0-9a-f]{40}$/', $clear) !== 1
            || $sanctioned === $clear
        ) {
            $this->error('Provide distinct, valid --sanctioned and --clear EVM addresses from the oracle test data.');

            return self::FAILURE;
        }

        $requested = (array) $this->option('network');
        $networks = $requested === []
            ? PaymentNetwork::cases()
            : array_map(fn (string $value): ?PaymentNetwork => PaymentNetwork::tryFrom($value), $requested);

        if (in_array(null, $networks, true)) {
            $this->error('Every --network must be ethereum or arbitrum.');

            return self::FAILURE;
        }

        $rows = [];
        $passed = true;

        foreach ($networks as $network) {
            if (! $network instanceof PaymentNetwork) {
                continue;
            }

            $positive = $screener->screen($this->subject($network, $sanctioned));
            $negative = $screener->screen($this->subject($network, $clear));
            $networkPassed = $positive->risk === ScreeningRisk::Sanctioned
                && $negative->risk === ScreeningRisk::NoMatch;

            $rows[] = [
                $network->label(),
                $positive->risk->value,
                $negative->risk->value,
                $networkPassed ? 'PASS' : 'FAIL',
            ];
            $passed = $passed && $networkPassed;
        }

        $this->table(['Network', 'Known sanctioned', 'Known clear', 'Result'], $rows);

        if (! $passed) {
            $this->error('Oracle verification failed. Keep BILLING_SCREENING_ENABLED=false.');

            return self::FAILURE;
        }

        $this->info('Oracle verification passed. Record the addresses and date in your deployment evidence.');

        return self::SUCCESS;
    }

    private function subject(PaymentNetwork $network, string $sender): ScreeningSubject
    {
        return new ScreeningSubject(
            network: $network,
            transactionHash: '0x'.str_repeat('0', 64),
            senderAddress: $sender,
            recipientAddress: '0x'.str_repeat('0', 40),
            asset: SettlementAsset::Eth,
            receivedAmount: '0',
        );
    }
}
