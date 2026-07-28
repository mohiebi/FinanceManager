/**
 * Optional, opt-in persistence of the unlocked data key.
 *
 * IndexedDB is used rather than sessionStorage for one reason: it can store a
 * `CryptoKey` object through the structured clone algorithm, so the key stays
 * **non-extractable**. JavaScript can go on using it, but nothing — including an
 * XSS payload — can ever read its bytes back out. sessionStorage would require an
 * extractable key, i.e. handing the raw secret to anyone who can run script.
 *
 * The cost this does carry is physical, not remote: with a stored key, whoever
 * holds the unlocked device can read the data without knowing the passphrase.
 * That is why it is opt-in, why it expires, and why logging out clears it.
 *
 * Deliberately free of imports: kept self-contained like the rest of lib/vault.
 */

const DB_NAME = 'cashpilot-vault';
const STORE = 'keys';
const RECORD_ID = 'dek';

/** How long a trusted device stays trusted without re-entering the passphrase. */
export const TRUST_DAYS = 7;

type StoredKey = {
    id: string;
    key: CryptoKey;
    /** Which vault this key belongs to, so a re-armed vault invalidates it. */
    fingerprint: string;
    expiresAt: number;
};

function open(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) {
                request.result.createObjectStore(STORE, { keyPath: 'id' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function transact<T>(
    mode: IDBTransactionMode,
    run: (store: IDBObjectStore) => IDBRequest,
): Promise<T | undefined> {
    return open().then(
        (db) =>
            new Promise<T | undefined>((resolve, reject) => {
                const request = run(
                    db.transaction(STORE, mode).objectStore(STORE),
                );

                request.onsuccess = () => resolve(request.result as T);
                request.onerror = () => reject(request.error);
            }),
    );
}

export async function rememberKey(
    key: CryptoKey,
    fingerprint: string,
    days: number = TRUST_DAYS,
): Promise<void> {
    const record: StoredKey = {
        id: RECORD_ID,
        key,
        fingerprint,
        expiresAt: Date.now() + days * 24 * 60 * 60 * 1000,
    };

    try {
        await transact('readwrite', (store) => store.put(record));
    } catch {
        // Private browsing and blocked storage both land here. Remembering is a
        // convenience, so failing to remember must never break unlocking.
    }
}

/**
 * The remembered key, if one is still valid for this vault.
 *
 * Returns null once it has expired or the vault has been re-armed under a
 * different key, and clears the stale record on the way out.
 */
export async function recallKey(
    fingerprint: string,
): Promise<CryptoKey | null> {
    try {
        const record = await transact<StoredKey>('readonly', (store) =>
            store.get(RECORD_ID),
        );

        if (record === undefined) {
            return null;
        }

        if (
            record.expiresAt < Date.now() ||
            record.fingerprint !== fingerprint
        ) {
            await forgetKey();

            return null;
        }

        return record.key;
    } catch {
        return null;
    }
}

export async function forgetKey(): Promise<void> {
    try {
        await transact('readwrite', (store) => store.delete(RECORD_ID));
    } catch {
        // Nothing to do — a key we cannot reach is a key we cannot use either.
    }
}
