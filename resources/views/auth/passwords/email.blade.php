<!DOCTYPE HTML>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
  <meta http-equiv="Content-Security-Policy" content="default-src * data: gap: https://ssl.gstatic.com; style-src * 'unsafe-inline'; script-src * 'unsafe-inline' 'unsafe-eval'">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="つばさ">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="/manifest.json">
  <link href="{{ mix('css/app.css') }}" rel="stylesheet">
  <link rel="apple-touch-icon" href="/appicon.png">
  <link rel="apple-touch-startup-image" href="/img/launch-1242x2208.png">
  <link rel="apple-touch-startup-image" href="/img/launch-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="/img/launch-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
  <link rel="shortcut icon" href="https://tsubasa.smartj.mobi/appicon.png">
</head>
<body class="bg-white">
  <ons-page id="login_page">
    <ons-toolbar class="navbar" class="bg-white">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center">
        <span class="white">パスワード再設定</span>
      </div>
    </ons-toolbar>
    <div class="row bg-white h-100p">
      <div class="col bg-white center" style="padding: 30% 0 0 0">
        @if (session('status'))
          <div class="alert alert-success" role="alert">
            {{ session('status') }}
          </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}"
              aria-label="パスワード再設定">
          @csrf

          <div class="form-group row">
            <div class="col-md-6">
              <input id="email" type="email"
                         modifier="border" placeholder="メールアドレス"
                         class="text-input text-input--border login_field form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                         name="email" value="{{ old('email') }}" required autofocus>

              @if ($errors->has('email'))
                <p class="invalid-feedback" role="alert">
                  <strong>{{ $errors->first('email') }}</strong>
                </p>
              @endif
            </div>
          </div>

          <div class="form-group row mt-30 mb-0">
            <div class="col-md-6 offset-md-4">
              <button type="submit" class="button">
                パスワード再設定用メール送信
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </ons-page>
  <script src="https://unpkg.com/onsenui/js/onsenui.min.js"></script>
</body>
</html>