export type NetworkName = 'ethereum' | 'arbitrum';
export type AssetName = 'eth' | 'usdt' | 'usdc';

/**
 * What an operation is for.
 *
 * A settlement moves a verified, screened payment. A recovery moves whatever is
 * stranded at an address we derived — a buyer who paid after their window
 * closed, or on the chain their intent did not name. Recoveries carry no
 * payment and therefore no screening, so they always land in the risk vault.
 */
export type OperationKind = 'settlement' | 'recovery';

export type ScreeningRisk = 'no_match' | 'flagged' | 'unscreened';

export type SettlementRequest = {
    kind: OperationKind;
    operationId: string;
    paymentId: string;
    network: NetworkName;
    chainId: number;
    derivationIndex: number;
    keyVersion: string;
    depositAddress: string;
    asset: AssetName;
    tokenContract: string | null;
    verifiedAmount: string;
    chainVerified: boolean;
    screeningRisk: ScreeningRisk;
    riskAuthorized: boolean;
};

export type Operation = SettlementRequest & {
    status: 'submitted' | 'processing' | 'completed' | 'retryable_failure' | 'needs_review' | 'failed';
    stage: string;
    createdAt: string;
    updatedAt: string;
    transactionHashes: Record<string, string>;
    signedTransactions: Record<string, string>;

    /**
     * The token amount this operation committed to swapping, pinned on the
     * first attempt so a retry re-signs the same trade even if the balance
     * moved underneath it.
     */
    settleAmount?: string;

    gasUsedWei?: string;
    gasTopupWei?: string;
    quotedEth?: string;
    minimumEth?: string;
    quoteExpiresAt?: string;
    receivedEth?: string;
    remainingTokenBalance?: string;
    remainingEthWei?: string;
    vaultReceipt?: string;
    failureCode?: string;
    failureReason?: string;
};
