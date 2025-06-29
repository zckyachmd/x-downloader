<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $tweetId = $request->get('tweetId');

        return view('welcome', [
            'tweetId' => $tweetId,
        ]);
    }
}
