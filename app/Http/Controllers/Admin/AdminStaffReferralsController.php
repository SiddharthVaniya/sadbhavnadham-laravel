<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminStaffReferralsData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStaffReferralsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render(
            'Admin/Referrals/Index',
            AdminStaffReferralsData::pageFromRequest($user, $request),
        );
    }
}
