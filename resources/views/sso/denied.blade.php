@component('layouts.guest', ['title' => __('sso.denied_title', ['app' => $appLabel])])
    <div class="flex size-12 items-center justify-center rounded-full bg-surface-muted text-danger">
        <x-icon name="exclamation-triangle" class="size-6" />
    </div>

    <h1 class="mt-5 text-2xl font-bold sm:text-3xl">{{ __('sso.denied_title', ['app' => $appLabel]) }}</h1>
    <p class="mt-3 text-base" role="alert">{{ $reason }}</p>
    <p class="mt-2 text-sm text-text-muted">{{ __('sso.denied_signed_in_as', ['name' => $name, 'email' => $email]) }}</p>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
        <x-button :href="route('dashboard')" variant="secondary" icon="arrow-left" class="w-full sm:w-auto">{{ __('sso.back_to_academic') }}</x-button>

        <form method="POST" action="{{ route('sso.switch-account') }}" class="w-full sm:w-auto">
            @csrf
            <input type="hidden" name="return" value="{{ $returnUrl }}">
            <x-button type="submit" icon="arrow-right-start-on-rectangle" class="w-full">{{ __('sso.switch_account') }}</x-button>
        </form>
    </div>
@endcomponent
