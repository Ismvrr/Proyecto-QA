<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function operators()
    {
        $user = Auth::user();
        
        // Contamos cuántos usuarios hay por cada rol
        $stats = User::where('company_id', $user->company_id)
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->get();

        return view('reports.operators', compact('stats'));
    }
}