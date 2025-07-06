<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $tweetUrl   = $request->input('tweet');
        $src        = $request->input('src');
        $autoSearch = false;

        if (str_starts_with($src, 'xdl_')) {
            $signature = substr($src, 4);
            $key       = 'sig:' . $signature;

            if (session()->pull($key)) {
                $autoSearch = true;
            }
        }

        return view('home', [
            'tweetUrl'   => $tweetUrl,
            'autoSearch' => $autoSearch,
        ]);
    }
}
