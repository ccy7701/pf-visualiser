import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import os from 'node:os';

const devServerPort = Number(process.env.VITE_DEV_SERVER_PORT ?? 9001);
const devServerHost = process.env.VITE_DEV_SERVER_HOST ?? getLanIpAddress() ?? 'localhost';

function getLanIpAddress() {
    let interfaces;

    try {
        interfaces = os.networkInterfaces();
    } catch {
        return null;
    }

    for (const addresses of Object.values(interfaces)) {
        for (const address of addresses ?? []) {
            if (address.family === 'IPv4' && !address.internal) {
                return address.address;
            }
        }
    }

    return null;
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: devServerPort,
        strictPort: true,
        origin: `http://${devServerHost}:${devServerPort}`,
        cors: {
            origin: true,
        },
        hmr: {
            host: devServerHost,
            port: devServerPort,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
