<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPartnerReportsData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerReportsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render(
            'Admin/PartnerReports/Index',
            AdminPartnerReportsData::pageFromRequest($user, $request),
        );
    }
}
