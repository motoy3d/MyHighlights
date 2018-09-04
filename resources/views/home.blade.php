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
  {{--Monaca用？  <script src="components/loader.js"></script>--}}
  {{--Monaca用？  <link rel="stylesheet" href="components/loader.css">--}}
  {{--<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">--}}
  <link href="{{ mix('css/app.css') }}" rel="stylesheet">
  <link rel="apple-touch-icon" sizes="192x192" href="appicon.png">
  <link rel="shortcut icon" href="https://smartj.mobi/appicon.png">
</head>
<body>
  <div id="app"></div>
  <script src="{{ mix('js/manifest.js') }}"></script>
  <script src="{{ mix('js/vendor.js') }}"></script>
  <script src="{{ mix('js/app.js') }}"></script>
  </body>
</html>
