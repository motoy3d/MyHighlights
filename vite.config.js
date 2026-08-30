import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';

// Laravel Mix(webpack)から移行。Laravel 13の標準ビルドツールであるViteを使う。
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/assets/sass/app.scss',
                'resources/assets/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            // コンポーネント内の画像参照はすべて /img/... /storage/... という
            // public配下の絶対パスであり、バンドル対象にしてはいけないため
            // アセットURLの書き換えを無効化する。
            template: {
                transformAssetUrls: false,
            },
        }),
    ],
    resolve: {
        alias: {
            // Laravel Mixと同じくテンプレートコンパイラ入りのフルビルドを使う
            vue: 'vue/dist/vue.esm.js',
        },
    },
});
