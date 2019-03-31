<!DOCTYPE HTML>
<html>
<head>
  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
      j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
      'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-KNDDCBG');</script>
  <!-- End Google Tag Manager -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
  <meta http-equiv="Content-Security-Policy" content="default-src * data: gap: https://ssl.gstatic.com; style-src * 'unsafe-inline'; script-src * 'unsafe-inline' 'unsafe-eval'">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="つばさ">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Tsubasa⬆︎UP - パスワード再設定</title>
  <link rel="manifest" href="/manifest.json">
  <link href="{{ mix('css/app.css') }}" rel="stylesheet">
  <link rel="apple-touch-icon" href="/appicon.png">
  <link rel="apple-touch-startup-image" href="img/launch-640x1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-828x1792.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-1242x2688.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-1125x2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-1536x2048.png" media="(min-device-width: 768px) and (max-device-width: 1024px) and (-webkit-min-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-1668x2224.png" media="(min-device-width: 834px) and (max-device-width: 834px) and (-webkit-min-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-2048x2732.png" media="(min-device-width: 1024px) and (max-device-width: 1024px) and (-webkit-min-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="shortcut icon" href="https://tsubasa.smartj.mobi/appicon.png">
</head>
<body class="bg-white">
  <!-- Google Tag Manager (noscript) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KNDDCBG"
                    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->
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
        @if ($errors->has('email'))
          <p class="invalid-feedback" role="alert">
            <strong>{{ $errors->first('email') }}</strong>
          </p>
        @endif

        <i id="sendBtnSpinner" class="fa fa-spinner fa-spin fa-3x" style="display:none"></i>
        <form method="POST" action="{{ route('password.email') }}"
              aria-label="パスワード再設定" onsubmit="submitForm()">
          @csrf
          <div class="form-group row mt-30">
            <div class="col-md-6">
              <input id="email" type="email"
                         modifier="border" placeholder="メールアドレス"
                         class="text-input text-input--border login_field form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                         name="email" value="{{ old('email') }}" required autofocus>
            </div>
          </div>

          <div class="form-group row mt-30 mb-0">
            <div class="col-md-6 offset-md-4">
              <button id="sendBtn" type="submit" class="button">
                パスワード再設定用メール送信
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </ons-page>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/onsen/2.10.6/js/onsenui.min.js"></script>
  <script>
    function submitForm() {
      document.getElementsByClassName('invalid-feedback')[0].remove();
      document.getElementById('sendBtn').setAttribute('disabled', true);
      document.getElementById('sendBtnSpinner').setAttribute('style', 'display:block');
    }
  </script>
</body>
</html>