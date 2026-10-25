<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Others\OpenID;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Authorization code flow of an OpenID Connect provider, with the endpoints of its discovery document.
 */
class OpenIDProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    protected array $endpoints = [];

    protected ?string $idToken = null;

    public function setEndpoints(array $endpoints): static
    {
        $this->endpoints = $endpoints;

        return $this;
    }

    public function getIdToken(): ?string
    {
        return $this->idToken;
    }

    public function getAccessTokenResponse($code)
    {
        $response = parent::getAccessTokenResponse($code);
        $this->idToken = $response['id_token'] ?? null;

        return $response;
    }

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->endpoints['authorization_endpoint'], $state);
    }

    protected function getTokenUrl()
    {
        return $this->endpoints['token_endpoint'];
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->endpoints['userinfo_endpoint'], [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $token],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'nickname' => $user['preferred_username'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }
}
