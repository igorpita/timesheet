import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import fs from 'fs';

export default defineConfig({
  plugins: [
    vue(),
    {
      name: 'copy-deck-integration',
      closeBundle() {
        fs.copyFileSync(
          resolve(__dirname, 'src/deck-integration.js'),
          resolve(__dirname, 'js/deck-integration.js')
        );
        if (fs.existsSync(resolve(__dirname, 'js/oab-timesheet.css'))) {
          fs.copyFileSync(
            resolve(__dirname, 'js/oab-timesheet.css'),
            resolve(__dirname, 'css/oab-timesheet.css')
          );
        }
      }
    }
  ],
  build: {
    outDir: 'js',
    emptyOutDir: false,
    lib: {
      entry: resolve(__dirname, 'src/main.js'),
      name: 'OABTimesheet',
      formats: ['iife'],
      fileName: () => 'oab-timesheet-main.js'
    },
    rollupOptions: {
      output: {
        extend: true
      }
    }
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production')
  }
});
