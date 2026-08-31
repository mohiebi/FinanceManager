import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import type { Operation } from './types.js';

export class OperationStore {
    private writeQueue: Promise<void> = Promise.resolve();

    public constructor(private readonly path: string) {}

    public async get(id: string): Promise<Operation | undefined> {
        return (await this.all())[id];
    }

    public async list(): Promise<Operation[]> {
        return Object.values(await this.all());
    }

    public put(operation: Operation): Promise<void> {
        const write = this.writeQueue.then(async () => {
            const operations = await this.all();
            operations[operation.operationId] = operation;
            await mkdir(dirname(this.path), { recursive: true });
            const temporary = `${this.path}.${process.pid}.tmp`;
            await writeFile(temporary, JSON.stringify(operations), {
                mode: 0o600,
            });
            await rename(temporary, this.path);
        });

        this.writeQueue = write.catch(() => undefined);

        return write;
    }

    private async all(): Promise<Record<string, Operation>> {
        try {
            const parsed: unknown = JSON.parse(await readFile(this.path, 'utf8'));

            return parsed !== null && typeof parsed === 'object' ? (parsed as Record<string, Operation>) : {};
        } catch (error) {
            if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
                return {};
            }

            throw error;
        }
    }
}
