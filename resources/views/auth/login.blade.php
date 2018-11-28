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
  <link rel="apple-touch-icon" href="appicon.png">
  <link rel="apple-touch-startup-image" href="img/launch-1242x2208.png">
  {{--<link rel="apple-touch-startup-image" href="img/launch-640x1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">--}}
  <link rel="apple-touch-startup-image" href="img/launch-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
  <link rel="apple-touch-startup-image" href="img/launch-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
  {{--<link rel="apple-touch-startup-image" href="img/launch-1125x2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">--}}
  {{--<link rel="apple-touch-startup-image" href="img/launch-1536x2048.png" media="(min-device-width: 768px) and (max-device-width: 1024px) and (-webkit-min-device-pixel-ratio: 2) and (orientation: portrait)">--}}
  {{--<link rel="apple-touch-startup-image" href="img/launch-1668x2224.png" media="(min-device-width: 834px) and (max-device-width: 834px) and (-webkit-min-device-pixel-ratio: 2) and (orientation: portrait)">--}}
  {{--<link rel="apple-touch-startup-image" href="img/launch-2048x2732.png" media="(min-device-width: 1024px) and (max-device-width: 1024px) and (-webkit-min-device-pixel-ratio: 2) and (orientation: portrait)">--}}
  <link rel="shortcut icon" href="https://tsubasa.smartj.mobi/appicon.png">
</head>
<body class="bg-white">
  <ons-page id="login_page">
    <ons-toolbar class="navbar" class="bg-white">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center">
        <span class="white">横浜SCつばさ　ログイン</span>
      </div>
    </ons-toolbar>
    <div class="row bg-white h-100p">
      <form method="POST" id="login_form" action="{{ route('login') }}" aria-label="{{ __('Login') }}">
        @csrf
        <div class="col bg-white center" style="padding: 30% 0 0 0">
          <div class="space">
            <ons-input id="email" type="email" modifier="border" placeholder="メールアドレス"
                       class="login_field form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                       name="email" value="{{ old('email') }}" required autofocus>
            </ons-input>
            @if ($errors->has('email'))
              <p class="invalid-feedback" role="alert">
                <strong>{{ $errors->first('email') }}</strong>
              </p>
            @endif
          </div>
          <div class="space">
            <ons-input id="password" type="password" modifier="border" placeholder="パスワード"
                       class="login_field form-control{{ $errors->has('password') ? ' is-invalid' : '' }}"
                       name="password" required>
            </ons-input>
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
  <script src="https://unpkg.com/onsenui/js/onsenui.min.js"></script>
  {{--<script src="{{ mix('js/manifest.js') }}"></script>--}}
  {{--<script src="{{ mix('js/vendor.js') }}"></script>--}}
  {{--<script src="{{ mix('js/app.js') }}"></script>--}}
</body>
</html>
