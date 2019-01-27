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
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta http-equiv="Content-Security-Policy" content="default-src * data: gap: https://ssl.gstatic.com; style-src * 'unsafe-inline'; script-src * 'unsafe-inline' 'unsafe-eval'">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="つばさ">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>横浜SCつばさ</title>
  <link rel="manifest" href="/manifest.json">
  <link href="{{ mix('css/app.css') }}" rel="stylesheet">
  <link rel="apple-touch-icon" href="/appicon.png">
  <link rel="apple-touch-startup-image" href="/img/launch-1242x2208.png">
  <link rel="apple-touch-startup-image" href="/img/launch-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="/img/launch-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
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
    <div class="col bg-white center" style="padding: 30px 0 0 0">

    <form method="POST" action="{{ route('password.request') }}" aria-label="{{ __('Reset Password') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group row">
          <label for="email" class="col-md-4 col-form-label text-md-right">
            メールアドレス</label>

          <div class="col-md-6">
            <input id="email" type="email"
                   class="text-input text-input--border login_field form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                   name="email" value="{{ $email ?? old('email') }}" required autofocus>

            @if ($errors->has('email'))
              <span class="invalid-feedback" role="alert">
                                  <strong>{{ $errors->first('email') }}</strong>
                              </span>
            @endif
          </div>
        </div>

        <div class="form-group row mt-10">
          <label for="password" class="col-md-4 col-form-label text-md-right">
            新パスワード(6文字以上)</label>

          <div class="col-md-6">
            <input id="password" type="password"
                   class="text-input text-input--border login_field form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password"
                   required>

            @if ($errors->has('password'))
              <span class="invalid-feedback" role="alert">
                                  <strong>{{ $errors->first('password') }}</strong>
                              </span>
            @endif
          </div>
        </div>

        <div class="form-group row mt-10">
          <label for="password-confirm"
                 class="col-md-4 col-form-label text-md-right">
            新パスワード確認(6文字以上)</label>

          <div class="col-md-6">
            <input id="password-confirm" type="password"
                   class="text-input text-input--border login_field form-control" name="password_confirmation"
                   required>
          </div>
        </div>

        <div class="form-group row mt-30 mb-0">
          <div class="col-md-6 offset-md-4">
            <button type="submit" class="button">
              パスワード再設定
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