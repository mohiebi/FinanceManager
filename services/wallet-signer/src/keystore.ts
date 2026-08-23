import { createCipheriv, createDecipheriv, randomBytes, scryptSync } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { HDNodeWallet, Mnemonic } from 'ethers';

type Keystore = {
    version: 1;
    keyVersion: string;
    depositXpub: string;
    salt: string;
    iv: string;
    tag: string;
    ciphertext: string;
};

export async function initializeKeystore(path: string, passphrase: string, keyVersion: string): Promise<string> {
    if (passphrase.length < 16) {
        throw new Error('Passphrase must contain at least 16 characters.');
    }

    const mnemonic = Mnemonic.fromEntropy(randomBytes(32)).phrase;
    const root = HDNodeWallet.fromMnemonic(Mnemonic.fromPhrase(mnemonic), 'm');
    const depositXpub = root.derivePath("m/44'/60'/0'").neuter().extendedKey;
    const salt = randomBytes(32);
    const iv = randomBytes(12);
    const key = scryptSync(passphrase, salt, 32, { N: 1 << 18, r: 8, p: 1, maxmem: 512 * 1024 * 1024 });
    const cipher = createCipheriv('aes-256-gcm', key, iv);
    const ciphertext = Buffer.concat([cipher.update(mnemonic, 'utf8'), cipher.final()]);
    const value: Keystore = {
        version: 1,
        keyVersion,
        depositXpub,
        salt: salt.toString('base64'),
        iv: iv.toString('base64'),
        tag: cipher.getAuthTag().toString('base64'),
        ciphertext: ciphertext.toString('base64'),
    };
    await writeFile(path, JSON.stringify(value), { flag: 'wx', mode: 0o600 });
    return mnemonic;
}

export async function readKeystore(path: string): Promise<Keystore> {
    return JSON.parse(await readFile(path, 'utf8')) as Keystore;
}

export async function unlockKeystore(path: string, passphrase: string): Promise<string> {
    const value = await readKeystore(path);
    const key = scryptSync(passphrase, Buffer.from(value.salt, 'base64'), 32, { N: 1 << 18, r: 8, p: 1, maxmem: 512 * 1024 * 1024 });
    const decipher = createDecipheriv('aes-256-gcm', key, Buffer.from(value.iv, 'base64'));
    decipher.setAuthTag(Buffer.from(value.tag, 'base64'));
    return Buffer.concat([
        decipher.update(Buffer.from(value.ciphertext, 'base64')),
        decipher.final(),
    ]).toString('utf8');
}

export async function derivePublicAddresses(path: string, start: number, count: number): Promise<{ keyVersion: string; addresses: Array<{ index: number; address: string }> }> {
    const value = await readKeystore(path);
    const branch = HDNodeWallet.fromExtendedKey(value.depositXpub).deriveChild(0);
    return {
        keyVersion: value.keyVersion,
        addresses: Array.from({ length: count }, (_, offset) => {
            const index = start + offset;
            return { index, address: branch.deriveChild(index).address.toLowerCase() };
        }),
    };
}

export function deriveDepositAddress(mnemonic: string, index: number): string {
    return HDNodeWallet.fromMnemonic(Mnemonic.fromPhrase(mnemonic), 'm')
        .derivePath(`m/44'/60'/0'/0/${index}`).address.toLowerCase();
}
