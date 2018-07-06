@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">Dashboard</div>

          <div class="card-body">
            @if (session('status'))
              <div class="alert alert-success" role="alert">
                {{ session('status') }}
              </div>
            @endif
          </div>
          <div class="columns medium-3" v-for="result in results.data" v-cloak>
            <div class="card">
              <div class="card-divider">
                @{{ result.title }}
              </div>
              <div class="card-section">
                <p>@{{ result.content }}.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
