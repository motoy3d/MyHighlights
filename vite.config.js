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
                // ログイン画面などSPA本体を読み込まないページ用
                'resources/assets/js/onsen.js',
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
    build: {
        // Vite 7 の既定(baseline-widely-available)は Safari 16 以上向けの構文を出す。
        // 旧サーバのアクセスログには iOS 14/15 の実機が残っている(2.5か月で13回)ので、
        // esbuild にそこまで下げて変換させる。ポリフィルは不要(使っている API は古い)。
        target: ['es2019', 'safari13', 'ios13'],
    },
    resolve: {
        alias: {
            // Laravel Mixと同じくテンプレートコンパイラ入りのフルビルドを使う
            vue: 'vue/dist/vue.esm.js',
        },
    },
});
