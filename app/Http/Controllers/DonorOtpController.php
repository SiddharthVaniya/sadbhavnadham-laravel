<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendDonorOtpRequest;
use App\Http\Requests\VerifyDonorOtpRequest;
use App\Models\Donor;
use App\Support\DonorOtpService;
use App\Support\DonorPortalSession;
use Illuminate\Http\JsonResponse;

class DonorOtpController extends Controller
{
    public function send(SendDonorOtpRequest $request, DonorOtpService $otpService): JsonResponse
    {
        $method = (string) $request->validated('login_method');

        $result = $method === 'email'
            ? $otpService->sendByEmail((string) $request->validated('donor_email'))
            : $otpService->send(
                (string) $request->validated('donor_phone'),
                (string) $request->validated('phone_dial_code'),
            );

        if (($result['found'] ?? false) === false) {
            return response()->json($result);
        }

        if (($result['sent'] ?? false) === false) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function verify(
        VerifyDonorOtpRequest $request,
        DonorOtpService $otpService,
        DonorPortalSession $portalSession,
    ): JsonResponse {
        $method = (string) $request->validated('login_method');

        $result = $method === 'email'
            ? $otpService->verifyByEmail(
                (string) $request->validated('donor_email'),
                (string) $request->validated('otp'),
            )
            : $otpService->verify(
                (string) $request->validated('donor_phone'),
                (string) $request->validated('otp'),
                (string) $request->validated('phone_dial_code'),
            );

        /** @var Donor $donor */
        $donor = $result['donor'];
        $portalSession->login($donor);

        return response()->json([
            'verified' => true,
            'signed_in' => true,
            'display_name' => $portalSession->toFrontend()['display_name'],
            'profile' => $result['profile'],
            'message' => $result['message'],
        ]);
    }

    public function session(DonorPortalSession $portalSession): JsonResponse
    {
        return response()->json($portalSession->toFrontend());
    }

    public function logout(DonorPortalSession $portalSession): JsonResponse
    {
        $portalSession->logout();

        return response()->json([
            'signed_in' => false,
            'message' => 'Signed out. You can continue as a guest.',
        ]);
    }
}
