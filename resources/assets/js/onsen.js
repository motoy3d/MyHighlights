// ログイン画面など、SPA本体(app.js)を読み込まないページ向けの Onsen UI。
//
// 以前は cdnjs から onsenui.min.js を読んでいたが、外部CDNに依存すると
// 障害時や改竄時にログイン画面が直接影響を受けるためバンドルに含める。
//
// 注意: `import 'onsenui'` （副作用のみのimport）ではカスタム要素が登録されない。
// Onsen UI のESM版は要素ごとにモジュールが分かれており、
// vue-onsenui と同じく使う要素を個別にimportする必要がある。
// これによりSPA本体とチャンクを共有できる（重複してバンドルされない）。
//
// Bladeで使う ons-* 要素を増やしたらここにも追加すること。
// tests/Feature/OnsenBundleTest.php で追従漏れを検査している。
import ons from 'onsenui/esm';
import 'onsenui/esm/elements/ons-page';
import 'onsenui/esm/elements/ons-toolbar';
import 'onsenui/esm/elements/ons-button';
import 'onsenui/esm/elements/ons-icon';

// CDN版と同じく、インラインスクリプトから ons.* を使えるようにしておく。
window.ons = ons;
