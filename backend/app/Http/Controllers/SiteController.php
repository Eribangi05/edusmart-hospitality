<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Plan;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function pricing()
    {
        return view('site.pricing', ['plans' => Plan::where('is_active', true)->where('key', '!=', 'trial')->orderBy('price_monthly')->get()]);
    }

    public function contact(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email', 'phone' => 'nullable|string|max:30', 'company' => 'nullable|string|max:150',
            'interest' => 'nullable|string|max:40', 'message' => 'required|string|max:3000', 'website' => 'prohibited']); // `website` is a honeypot
        ContactMessage::create($d + ['ip' => $r->ip()]);

        return response()->json(['message' => 'Thank you. We will reply within one business day.'], 201);
    }
}
