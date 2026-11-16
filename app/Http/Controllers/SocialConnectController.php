<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Meta\MetaApiException;
use App\Services\Meta\MetaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Facebook Login round-trip. After Meta sends the user back, we list the Pages,
 * Instagram accounts and ad accounts they can access and hand them to the
 * selection screen (SocialConnectSelect), which saves the ones they pick.
 */
class SocialConnectController extends Controller
{
    public function redirect(Request $request, MetaClient $meta)
    {
        abort_unless($request->user()->can('social-connect'), 403);

        if (!MetaClient::isConfigured()) {
            return to_route('social')->with('error', 'Meta app is not configured. Set META_APP_ID and META_APP_SECRET in .env.');
        }

        $company = Company::visibleTo($request->user())->findOrFail($request->query('company'));

        $state = Str::random(40);
        session(['meta_oauth' => ['state' => $state, 'company_id' => $company->id]]);

        return redirect()->away($meta->loginUrl(route('social.callback'), $state));
    }

    public function callback(Request $request, MetaClient $meta)
    {
        abort_unless($request->user()->can('social-connect'), 403);

        $oauth = session()->pull('meta_oauth');
        if (!$oauth || !hash_equals($oauth['state'], (string) $request->query('state'))) {
            return to_route('social')->with('error', 'The Facebook login session expired. Please try again.');
        }

        if ($request->filled('error')) {
            return to_route('social', ['company' => $oauth['company_id']])
                ->with('error', 'Facebook connection was cancelled.');
        }

        try {
            $token = $meta->exchangeCode((string) $request->query('code'), route('social.callback'));
            $userToken = $token['access_token'];

            $pages = iterator_to_array($meta->paginate('me/accounts', [
                'fields' => 'id,name,access_token,picture{url},instagram_business_account{id,username,name,profile_picture_url}',
                'limit' => 100,
            ], $userToken), false);

            $adAccounts = iterator_to_array($meta->paginate('me/adaccounts', [
                'fields' => 'id,name,account_status,currency',
                'limit' => 100,
            ], $userToken), false);
        } catch (MetaApiException $e) {
            return to_route('social', ['company' => $oauth['company_id']])
                ->with('error', 'Facebook error: ' . $e->getMessage());
        }

        // Tokens stay server-side, encrypted, until the user confirms the selection.
        session(['meta_connect' => encrypt([
            'company_id' => $oauth['company_id'],
            'user_token' => $userToken,
            'expires_in' => $token['expires_in'],
            'pages' => $pages,
            'ad_accounts' => $adAccounts,
        ])]);

        return to_route('social.select');
    }
}
