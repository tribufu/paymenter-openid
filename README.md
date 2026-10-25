# OpenID Connect for Paymenter

Sign in to [Paymenter](https://paymenter.org) with any OpenID Connect provider, such as Keycloak, Authentik, Zitadel,
Auth0, or Google. It adds a button to the login page and uses the authorization code flow with PKCE, reading the
endpoints from the discovery document of the provider.

- Each Paymenter account is bound to the issuer and subject of a user of the provider, in the `ext_openid_identities`
  table, so a change of email in the provider keeps the account.
- A first sign in needs a verified email (`email_verified`). It creates the Paymenter account, unless registration is
  disabled in the settings of Paymenter.
- When an account already has that email, the sign in is refused, unless **Link Existing Accounts** is enabled. Enable
  it only for a provider you trust to verify emails, since whoever controls an email in the provider takes over its
  Paymenter account, administrators included. An account already bound to another user of the provider is never taken
  over.
- Accounts with two-factor authentication still go through it after the provider.
- A failed sign in shows its own page instead of going back to the login page, so a provider that keeps its session
  cannot loop.

Tested with Paymenter v1.5.9.

## Installation

Copy the files of this repository to `extensions/Others/OpenID` of Paymenter; the directory must have this name:

```bash
git clone https://github.com/tribufu/paymenter-openid.git extensions/Others/OpenID
```

Enable **OpenID Connect** in `/admin`, under Extensions, which runs its migration, and fill in its settings.

## Provider

Create a confidential client in the provider, with the scopes `openid`, `profile`, and `email`, and this redirect URI:

```
https://<paymenter>/openid/callback
```

| Setting                | Description                                                                         |
| ---------------------- | ----------------------------------------------------------------------------------- |
| Issuer URL             | The discovery document is read from `<issuer>/.well-known/openid-configuration`.    |
| Client ID              | Client of the provider.                                                             |
| Client Secret          | Secret of the client, stored encrypted.                                             |
| Provider Name          | Shown on the button, as in "Sign in with Keycloak".                                 |
| Link Existing Accounts | Binds the account that already has the verified email on a first sign in. Off by default. |

The issuer may be an internal address, such as `http://keycloak:8080/realms/example`, when the provider gives the
browser its public address in the discovery document, as Keycloak does with its hostname set.

## Logout of the provider

Logging out of Paymenter keeps the session of the provider. Another extension can end it too, with the ID token the
sign in keeps in the session:

```php
use Illuminate\Support\Facades\Session;
use Paymenter\Extensions\Others\OpenID\OpenID;

$idToken = Session::get('openid.id_token');
// After logging out of Paymenter:
return redirect()->away((new OpenID)->endSessionUrl($idToken));
```

The provider must allow the address of Paymenter as a post logout redirect URI.

## License

[MIT](LICENSE.txt)
