<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/example', function (Request $request) {
    return response()->json([
        'status' => 'success',
        'method' => 'GET',
        'message' => 'Laravel API connected successfully.',
        'query' => $request->query('q'),
        'timestamp' => now()->toDateTimeString(),
    ]);
});

Route::post('/echo', function (Request $request) {
    return response()->json([
        'status' => 'success',
        'method' => 'POST',
        'message' => 'Data received successfully.',
        'received' => $request->input('name'),
        'timestamp' => now()->toDateTimeString(),
    ]);
});
