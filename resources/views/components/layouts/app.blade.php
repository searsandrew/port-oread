<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="nativephp-safe-area min-h-screen bg-black">
        <div class="space"></div>
        {{ $slot }}
        <native:bottom-nav label-visibility="labeled">
            <native:bottom-nav-item
                id="friends"
                icon="connections"
                label="Friends"
                url="/friends"
            />
            <native:bottom-nav-item
                id="collection"
                icon="{{ \Native\Mobile\Facades\System::isIos() ? 'archivebox' : 'inventory_2' }}"
                label="Collection"
                url="/collection"
            />
            <native:bottom-nav-item
                id="fleet"
                icon="star"
                label="Fleet"
                url="/fleet"
            />
            <native:bottom-nav-item
                id="shop"
                icon="store"
                label="Shop"
                url="/shop"
            />
        </native:bottom-nav>
        @fluxScripts
    </body>
</html>
