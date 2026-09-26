/**
 * 一時的な診断(iPhone でアプリを開いている間の通知。確認後に消す)。
 * sw.js からの知らせが画面に届くかを、postMessage と BroadcastChannel の両方で調べ、サーバのアクセス記録に残す。
 */
function diag(e, extra) {
  const q = new URLSearchParams(Object.assign({ e, v: 'd1', t: Date.now() }, extra || {}));
  fetch('/__diag?' + q.toString(), { cache: 'no-store' }).catch(() => {});
}

export function installDiag() {
  if (!('serviceWorker' in navigator)) {
    return;
  }
  diag('page-ready', { ctl: navigator.serviceWorker.controller ? 1 : 0, bc: ('BroadcastChannel' in window) ? 1 : 0 });
  navigator.serviceWorker.addEventListener('message', (event) => {
    const d = event.data || {};
    diag('page-msg', { via: 'pm', type: d.type || '' });
  });
  if ('BroadcastChannel' in window) {
    new BroadcastChannel('tsubasa-diag').onmessage = (event) => {
      const d = event.data || {};
      diag('page-msg', { via: 'bc', type: d.type || '', nid: d.nid || '' });
    };
  }
  document.addEventListener('visibilitychange', () => diag('vis', { s: document.visibilityState }));
}
