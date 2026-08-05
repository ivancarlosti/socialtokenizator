<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class AboutController extends Controller
{
    public function show()
    {
        $locale = app()->getLocale();
        $aboutContent = (string) Setting::get("about_page_{$locale}", '');

        return view('about', [
            'aboutContent' => $aboutContent,
        ]);
    }
}
