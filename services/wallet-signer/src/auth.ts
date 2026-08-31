import { createHash, createHmac, timingSafeEqual } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';

export class RequestAuthenticator {
    private nonces = new Map<string, number>();
    private persistQueue: Promise<void> = Promise.resolve();

    public constructor(
        private readonly secretFile: string,
        private readonly nonceFile: string,
    ) {}

    public async initialize(): Promise<void> {
        try {
            const values = JSON.parse(await readFile(this.nonceFile, 'utf8')) as Record<string, number>;
            this.nonces = new Map(Object.entries(values));
        } catch (error) {
            if ((error as NodeJS.ErrnoException).code !== 'ENOENT') {
                throw error;
            }
        }
    }

    public async verify(headers: Record<string, string | string[] | undefined>, method: string, path: string, body: string): Promise<boolean> {
        const timestamp = String(headers['x-signer-timestamp'] ?? '');
        const nonce = String(headers['x-signer-nonce'] ?? '');
        const signature = String(headers['x-signer-signature'] ?? '');
        const timestampNumber = Number(timestamp);
        const now = Math.floor(Date.now() / 1000);

        if (!/^\d+$/.test(timestamp) || Math.abs(now - timestampNumber) > 300 || !/^[0-9a-f-]{36}$/i.test(nonce) || !/^[0-9a-f]{64}$/i.test(signature)) {
            return false;
        }

        this.prune(now);

        // Claimed here rather than after the signature check. Reading the secret
        // below yields the event loop, so two concurrent copies of one request
        // would otherwise both pass this test and both be accepted. A request
        // that then fails to authenticate hands the nonce back.
        if (this.nonces.has(nonce)) {
            return false;
        }

        this.nonces.set(nonce, timestampNumber);

        if (!(await this.hasValidSignature(timestamp, nonce, method, path, body, signature))) {
            this.nonces.delete(nonce);

            return false;
        }

        const snapshot = JSON.stringify(Object.fromEntries(this.nonces));
        const persist = this.persistQueue.then(() => writeFile(this.nonceFile, snapshot, { mode: 0o600 }));
        this.persistQueue = persist.catch(() => undefined);
        await persist;

        return true;
    }

    private async hasValidSignature(timestamp: string, nonce: string, method: string, path: string, body: string, signature: string): Promise<boolean> {
        const secret = (await readFile(this.secretFile, 'utf8')).trim();

        if (secret.length < 32) {
            return false;
        }

        const canonical = [timestamp, nonce, method, path, createHash('sha256').update(body).digest('hex')].join('\n');
        const expected = createHmac('sha256', secret).update(canonical).digest();
        const supplied = Buffer.from(signature, 'hex');

        return supplied.length === expected.length && timingSafeEqual(supplied, expected);
    }

    private prune(now: number): void {
        for (const [nonce, timestamp] of this.nonces) {
            if (now - timestamp > 600) {
                this.nonces.delete(nonce);
            }
        }
    }
}
