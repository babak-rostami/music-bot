<?php

use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
