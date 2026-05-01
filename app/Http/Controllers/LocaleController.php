<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        if (Locales::isSupported($locale)) {
            $request->session()->put(Locales::SESSION_KEY, $locale);
        }

        return redirect()->back()->withInput($request->except('_token'));
    }
}
