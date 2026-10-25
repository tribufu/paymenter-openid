<x-app-layout>
    <x-slot name="title">
        {{ __('Could not sign in') }}
    </x-slot>

    <div class="container flex flex-col items-center justify-center text-center py-20">
        <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
            {{ __('Could not sign in') }}
        </h1>
        <p class="mt-6 text-lg font-medium text-pretty text-base/50">
            {{ $message }}
        </p>
        <div class="mt-10 flex items-center justify-center gap-x-6">
            <a href="{{ route('extensions.others.openid.redirect') }}">
                <x-button.primary>
                    {{ __('Try again') }}
                </x-button.primary>
            </a>
            <a href="{{ route('home') }}">
                <x-button.secondary>
                    {{ __('Go to home') }}
                </x-button.secondary>
            </a>
        </div>
    </div>
</x-app-layout>
