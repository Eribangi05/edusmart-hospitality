import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/auth.js', 'resources/js/console/main.js', 'resources/js/platform.js', 'resources/js/site.js', 'resources/js/workspaces.js', 'resources/js/terminal.js', 'resources/js/kds.js', 'resources/js/desk.js', 'resources/js/staff.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
