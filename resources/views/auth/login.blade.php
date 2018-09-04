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
  <!--Monaca用？  <script src="components/loader.js"></script>-->
  <!--Monaca用？  <link rel="stylesheet" href="components/loader.css">-->
  <!--  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">-->
  <link rel="apple-touch-icon" sizes="192x192" href="appicon.png">
  <link rel="shortcut icon" href="https://smartj.mobi/appicon.png">
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">{{ __('Login') }}</div>

          <div class="card-body">
            <form method="POST" action="{{ route('login') }}" aria-label="{{ __('Login') }}">
              @csrf

              <div class="form-group row">
                <label for="email" class="col-sm-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                <div class="col-md-6">
                  <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                         name="email" value="{{ old('email') }}" required autofocus>

                  @if ($errors->has('email'))
                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                  @endif
                </div>
              </div>

              <div class="form-group row">
                <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>

                <div class="col-md-6">
                  <input id="password" type="password"
                         class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password"
                         required>

                  @if ($errors->has('password'))
                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                  @endif
                </div>
              </div>

              <div class="form-group row">
                <div class="col-md-6 offset-md-4">
                  <div class="checkbox">
                    <label>
                      <input type="checkbox"
                             name="remember" {{ old('remember') ? 'checked' : '' }}> {{ __('Remember Me') }}
                    </label>
                  </div>
                </div>
              </div>

              <div class="form-group row mb-0">
                <div class="col-md-8 offset-md-4">
                  <button type="submit" class="btn btn-primary">
                    {{ __('Login') }}
                  </button>

                  <a class="btn btn-link" href="{{ route('password.request') }}">
                    {{ __('Forgot Your Password?') }}
                  </a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{--<script src="{{ mix('js/manifest.js') }}"></script>--}}
  {{--<script src="{{ mix('js/vendor.js') }}"></script>--}}
  {{--<script src="{{ asset('js/app.js') }}" defer></script>--}}
</body>
</html>