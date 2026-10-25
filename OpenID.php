<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Others\OpenID;

use App\Actions\Auth\Login;
use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Paymenter\Extensions\Others\OpenID\Models\Identity;

#[ExtensionMeta(
    name: 'OpenID Connect',
    description: 'Sign in with an OpenID Connect provider.',
    version: '1.0.0',
    author: 'Tribufu',
    url: 'https://github.com/tribufu/paymenter-openid',
)]
class OpenID extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'issuer',
                'label' => 'Issuer URL',
                'type' => 'text',
                'required' => true,
                'description' => 'The discovery document is read from <issuer>/.well-known/openid-configuration. It may be an internal address when the provider gives the browser its public one, as Keycloak does.',
            ],
            [
                'name' => 'client_id',
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'client_secret',
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'provider_name',
                'label' => 'Provider Name',
                'type' => 'text',
                'required' => true,
                'default' => 'OpenID',
                'description' => 'Shown on the sign in button.',
            ],
            [
                'name' => 'link_existing_accounts',
                'label' => 'Link Existing Accounts',
                'type' => 'checkbox',
                'required' => false,
                'description' => 'On a first sign in, bind the account that already has the verified email of the user. Enable it only for a provider you trust to verify emails, since whoever controls an email there takes over its account here, administrators included.',
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/OpenID/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/OpenID/database/migrations');
    }

    public function upgraded($oldVersion = null)
    {
        $this->installed();
    }

    public function boot()
    {
        require __DIR__ . '/routes/web.php';
        View::addNamespace('openid', __DIR__ . '/resources/views');
        app('translator')->addJsonPath(__DIR__ . '/lang');

        Event::listen('auth.login', function () {
            return ['view' => view('openid::button', ['name' => $this->config('provider_name')])];
        });
    }

    public function redirect(): RedirectResponse
    {
        return $this->provider()->redirect();
    }

    // A failed sign in shows its own page instead of going back to the login page, which may lead to the provider
    // again and loop while the provider keeps its session.
    public function callback(Request $request): RedirectResponse|ViewContract
    {
        $name = $this->config('provider_name');

        if ($request->has('error')) {
            return $this->error(__('Sign in with :name was cancelled.', ['name' => $name]));
        }

        $provider = $this->provider();

        try {
            $oauthUser = $provider->user();
        } catch (InvalidStateException) {
            return $this->error(__('Your sign in expired. Please try again.'));
        } catch (GuzzleException $exception) {
            report($exception);

            return $this->error(__(':name could not be reached. Please try again later.', ['name' => $name]));
        }

        // The account is the one bound to the subject the provider gives the user, which outlives a change of email.
        $issuer = $this->endpoints()['issuer'];
        $user = Identity::where('issuer', $issuer)->where('subject', $oauthUser->getId())->first()?->user;

        if (!$user) {
            $email = $oauthUser->getEmail();

            // Some providers send the claim as a string.
            $verified = in_array($oauthUser->user['email_verified'] ?? false, [true, 'true'], true);

            if (!is_string($email) || $email === '' || !$verified) {
                return $this->error(__('Verify the email address of your :name account, then try again.', ['name' => $name]));
            }

            $user = User::where('email', $email)->first();

            if ($user) {
                // Binding by email trusts the provider with the account of that email, so it is opt in.
                if (!$this->config('link_existing_accounts')) {
                    return $this->error(__('This email address already has an account here.'));
                }

                // An account already bound to another user of the provider is not taken over by its email.
                if (Identity::where('issuer', $issuer)->where('user_id', $user->id)->exists()) {
                    return $this->error(__('This email address belongs to another :name account.', ['name' => $name]));
                }
            } else {
                if (config('settings.registration_disabled', false)) {
                    return $this->error(__('Registration is disabled.'));
                }

                $user = User::create([
                    'email' => $email,
                    // The account pages of Paymenter need both names as text, even when the provider has no last name.
                    'first_name' => $oauthUser->user['given_name'] ?? $oauthUser->getNickname() ?? '',
                    'last_name' => $oauthUser->user['family_name'] ?? '',
                    'password' => Hash::make(Str::random(64)),
                    'email_verified_at' => now(),
                ]);
            }

            Identity::create(['user_id' => $user->id, 'issuer' => $issuer, 'subject' => $oauthUser->getId()]);
        }

        // Kept for a logout that also ends the session of the provider, with endSessionUrl.
        Session::put('openid.id_token', $provider->getIdToken());

        if ($user->tfa_secret) {
            Session::put('2fa', [
                'user_id' => $user->id,
                'remember' => true,
                'expires' => now()->addMinutes(5),
            ]);

            return redirect()->route('2fa');
        }

        (new Login)->execute($user, true);

        return redirect()->intended(route('dashboard'));
    }

    private function error(string $message): ViewContract
    {
        return view('openid::error', ['message' => $message]);
    }

    /**
     * Address that ends the session of the provider and sends the browser back to Paymenter.
     */
    public function endSessionUrl(?string $idToken): string
    {
        $parameters = [
            'client_id' => $this->config('client_id'),
            'post_logout_redirect_uri' => url('/'),
        ];

        if ($idToken) {
            $parameters['id_token_hint'] = $idToken;
        }

        return $this->endpoints()['end_session_endpoint'] . '?' . http_build_query($parameters);
    }

    private function provider(): OpenIDProvider
    {
        return Socialite::buildProvider(OpenIDProvider::class, [
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'redirect' => route('extensions.others.openid.callback'),
        ])->setEndpoints($this->endpoints())->enablePKCE();
    }

    private function endpoints(): array
    {
        $issuer = rtrim($this->config('issuer'), '/');

        return Cache::remember('openid.discovery.' . sha1($issuer), now()->addHour(), function () use ($issuer) {
            return Http::get($issuer . '/.well-known/openid-configuration')->throw()->json();
        });
    }
}
