import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  build: {
    outDir: path.resolve(__dirname, 'assets/dist'),
    emptyOutDir: true,
    rollupOptions: {
      input: {
        frontend: path.resolve(__dirname, 'assets/js/frontend.js'),
        admin: path.resolve(__dirname, 'assets/js/admin.js'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
