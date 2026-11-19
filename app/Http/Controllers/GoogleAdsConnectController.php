<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Google sign-in round-trip for Google Ads. After Google sends the user back,
 * we list the Google Ads accounts they can read (including client accounts of
 * their manager accounts) and hand them to GoogleAdsConnectSelect.
 */
class GoogleAdsConnectController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless($request->user()->can('social-connect'), 403);

        $company = Company::visibleTo($request->user())->findOrFail($request->query('company'));

        if (!$company->hasGoogleAdsApp()) {
            return to_route('social', ['company' => $company->id])
                ->with('error', "Add {$company->name}'s Google Ads developer token and OAuth client in the company settings first.");
        }

        $state = Str::random(40);
        session(['google_ads_oauth' => ['state' => $state, 'company_id' => $company->id]]);

        return redirect()->away(GoogleAdsClient::forCompany($company)->loginUrl(route('social.google.callback'), $state));
    }

    public function callback(Request $request)
    {
        abort_unless($request->user()->can('social-connect'), 403);

        $oauth = session()->pull('google_ads_oauth');
        if (!$oauth || !hash_equals($oauth['state'], (string) $request->query('state'))) {
            return to_route('social')->with('error', 'The Google sign-in session expired. Please try again.');
        }

        if ($request->filled('error')) {
            return to_route('social', ['company' => $oauth['company_id']])
                ->with('error', 'Google Ads connection was cancelled.');
        }

        try {
            $company = Company::visibleTo($request->user())->findOrFail($oauth['company_id']);
            $google = GoogleAdsClient::forCompany($company);

            $token = $google->exchangeCode((string) $request->query('code'), route('social.google.callback'));
            if (empty($token['refresh_token'])) {
                return to_route('social', ['company' => $company->id])->with('error',
                    'Google did not grant offline access. Remove the app at myaccount.google.com/permissions and connect again.');
            }

            $found = $google->discoverAccounts($token['refresh_token']);
        } catch (GoogleAdsException $e) {
            return to_route('social', ['company' => $oauth['company_id']])
                ->with('error', 'Google Ads error: ' . $e->getMessage());
        }

        // The refresh token stays server-side, encrypted, until the user confirms the selection.
        session(['google_ads_connect' => encrypt([
            'company_id' => $company->id,
            'refresh_token' => $token['refresh_token'],
            'accounts' => $found['accounts'],
            'skipped' => $found['skipped'],
        ])]);

        return to_route('social.google.select');
    }
}
