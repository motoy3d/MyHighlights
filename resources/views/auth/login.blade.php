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
  <title>Tsubasa⬆︎UP - ログイン</title>
  <link rel="manifest" href="/manifest.json">
  <link href="{{ mix('css/app.css') }}" rel="stylesheet">
  <link rel="apple-touch-icon" href="appicon.png">
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
        <img src="/img/tsubasa-up-logo.png" width="180" class="mt-5">
      </div>
    </ons-toolbar>
    <div class="row bg-white h-100p">
      <form method="POST" id="login_form" action="{{ route('login') }}" aria-label="{{ __('Login') }}">
        @csrf
        <div class="col bg-white center" style="padding: 30% 0 0 0">
          <div class="space">
            <input id="email" type="email" modifier="border" placeholder="メールアドレス"
                       class="text-input text-input--border login_field form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                       name="email" value="{{ old('email') }}" required autofocus>
            </input>
            @if ($errors->has('email'))
              <p class="invalid-feedback" role="alert">
                <strong>{{ $errors->first('email') }}</strong>
              </p>
            @endif
          </div>
          <div class="space">
            <input id="password" type="password" modifier="border" placeholder="パスワード"
                       class="text-input text-input--border login_field form-control{{ $errors->has('password') ? ' is-invalid' : '' }}"
                       name="password" required>
            </input>
            @if ($errors->has('password'))
              <p class="invalid-feedback" role="alert">
                <strong>{{ $errors->first('password') }}</strong>
              </p>
            @endif
          </div>
          <div class="center space mt-20">
            <ons-button id="login_btn" class="login_btn" modifier="large"
                        onclick="document.getElementById('login_form').submit()">ログイン</ons-button>
          </div>
          <div class="center mt-20">
            <a href="password/reset" class="gray small">パスワードを忘れた場合はこちら</a>
          </div>
        </div>
        <div class="space">
          <p class="red small">iOS 9以下では利用できません。PC(Chrome)をご使用ください。</p>
          <p class="small grey">ログインできない方は、<a href="mailto:motoy3d@gmail.com">motoy3d@gmail.com</a> までメールしてください。</p>
        </div>
        {{--<hr class="login_hr">--}}
        {{--<div class="row pb-50">--}}
          {{--<div class="center space">--}}
            {{--<ons-button id="google_login_btn" class="login_btn" modifier="large">--}}
              {{--<ons-icon icon="fa-google" size="20px" class="mr-10"></ons-icon>Googleでログイン</ons-button>--}}
          {{--</div>--}}
          {{--<div class="center space">--}}
            {{--<ons-button id="line_login_btn" class="login_btn" modifier="large">--}}
              {{--<ons-icon icon="fa-line" size="20px" class="mr-10"></ons-icon>LINEでログイン</ons-button>--}}
          {{--</div>--}}
          {{--<div class="center space">--}}
            {{--<ons-button id="facebook_login_btn" class="login_btn" modifier="large">--}}
              {{--<ons-icon icon="fa-facebook" size="20px" class="mr-10"></ons-icon>facebookでログイン</ons-button>--}}
          {{--</div>--}}
          {{--<div class="center space">--}}
            {{--<ons-button id="twitter_login_btn" class="login_btn" modifier="large">--}}
              {{--<ons-icon icon="fa-twitter" size="20px" class="mr-10"></ons-icon>twitterでログイン</ons-button>--}}
          {{--</div>--}}
        {{--</div>--}}
      </form>
    </div>
  </ons-page>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/onsen/2.10.6/js/onsenui.min.js"></script>
</body>
</html>
