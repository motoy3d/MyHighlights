<?php

  namespace Tests;

  use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

  abstract class TestCase extends BaseTestCase
  {
    use CreatesApplication;

    function json_enc($obj)
    {
      return json_encode($obj,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
  }
