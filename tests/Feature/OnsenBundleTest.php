<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ログイン画面などSPA本体を読み込まないBladeは、Onsen UI を
 * resources/assets/js/onsen.js から読み込んでいる。
 *
 * Onsen UI のESM版は要素ごとにモジュールが分かれており、使う要素を
 * 個別にimportしないとカスタム要素が登録されず、素の <ons-page> が
 * そのまま表示されてしまう（CSSも当たらない）。
 * Bladeに ons-* 要素を足したときの追従漏れをここで検出する。
 */
class OnsenBundleTest extends TestCase
{
    private const ENTRY = 'resources/assets/js/onsen.js';

    /** @return list<string> onsen.js を読み込んでいるBladeのパス */
    private function bladesUsingOnsenEntry(): array
    {
        $blades = [];
        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($dir as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            if (str_contains((string) file_get_contents($file->getPathname()), self::ENTRY)) {
                $blades[] = $file->getPathname();
            }
        }

        sort($blades);

        return $blades;
    }

    public function test_onsen_エントリを使うbladeが存在する(): void
    {
        $this->assertNotEmpty(
            $this->bladesUsingOnsenEntry(),
            'onsen.js を @vite で読み込むBladeが1つも無い。'
            .'CDNに戻っていないか確認すること'
        );
    }

    public function test_bladeで使う全てのons要素がonsen_jsでimportされている(): void
    {
        $entry = (string) file_get_contents(base_path(self::ENTRY));

        foreach ($this->bladesUsingOnsenEntry() as $blade) {
            $source = (string) file_get_contents($blade);
            // Bladeのコメント {{-- ... --}} 内は描画されないので除外する
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            preg_match_all('/<(ons-[a-z0-9-]+)/', $source, $matches);

            foreach (array_unique($matches[1]) as $element) {
                $this->assertStringContainsString(
                    "onsenui/esm/elements/{$element}",
                    $entry,
                    basename($blade)." が <{$element}> を使っているが、"
                    .self::ENTRY." でimportされていない。"
                    ."importしないとカスタム要素が登録されず表示が崩れる"
                );
            }
        }
    }

    public function test_bladeがcdnからonsen_uiを読み込んでいない(): void
    {
        foreach ($this->bladesUsingOnsenEntry() as $blade) {
            $this->assertStringNotContainsString(
                'cdnjs.cloudflare.com',
                (string) file_get_contents($blade),
                basename($blade).' が外部CDNを参照している'
            );
        }
    }
}
