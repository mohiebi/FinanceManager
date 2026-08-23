export type NetworkName = 'ethereum' | 'arbitrum';
export type AssetName = 'eth' | 'usdt' | 'usdc';

export type SettlementRequest = {
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
    screeningRisk: 'no_match' | 'flagged';
    riskAuthorized: boolean;
};

export type Operation = SettlementRequest & {
    status: 'submitted' | 'processing' | 'completed' | 'retryable_failure' | 'needs_review' | 'failed';
    stage: string;
    createdAt: string;
    updatedAt: string;
    transactionHashes: Record<string, string>;
    signedTransactions: Record<string, string>;
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
