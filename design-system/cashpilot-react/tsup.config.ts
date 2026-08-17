import { defineConfig } from 'tsup';

export default defineConfig({
    entry: ['src/index.ts'],
    format: ['esm'],
    dts: true,
    sourcemap: false,
    clean: false,
    treeshake: true,
    external: ['react', 'react-dom'],
    target: 'es2020',
});
