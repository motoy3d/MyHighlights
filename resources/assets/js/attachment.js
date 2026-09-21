/**
 * 添付ファイルの送信前チェック。
 *
 * iPhoneのホーム画面アプリ(PWA)などで、写真を選んだ後にアプリが
 * バックグラウンドへ回るとファイルの中身が読めなくなり、0バイトの
 * ファイルが送られてしまうことがある(#45)。サーバ側でも弾いているが、
 * 送信前に気づけるようここでも確認する。
 */

/**
 * 0バイトのファイルがあれば、ユーザー向けのメッセージを返す。無ければ null。
 * @param {FileList|File[]} files
 * @returns {string|null}
 */
export function emptyFileMessage(files) {
  const names = [];
  for (let i = 0; i < files.length; i++) {
    if (files[i].size === 0) {
      names.push(files[i].name);
    }
  }
  if (names.length === 0) {
    return null;
  }
  // ons.notification.alert の message は改行が反映されないため1行でつなげる
  return '空のファイル（0バイト）は添付できません（' + names.join('、') + '）。' +
    'その添付を外して、もう一度選び直してください。iPhoneの場合は写真を選び直してください。';
}

/**
 * 422(バリデーションエラー)のレスポンスから、最初のエラーメッセージを取り出す。
 * @param error axiosのエラー
 * @returns {string|null}
 */
export function validationErrorMessage(error) {
  if (!error.response || error.response.status !== 422 || !error.response.data) {
    return null;
  }
  const errors = error.response.data.errors;
  if (errors) {
    for (const key in errors) {
      if (errors[key] && errors[key].length) {
        return errors[key][0];
      }
    }
  }
  return error.response.data.message || null;
}
