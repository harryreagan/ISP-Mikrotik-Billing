@if (siteUrlSettings('site_logo'))
    <a href="{{ url('/') }}" style="display: inline-flex; align-items: center; text-decoration: none;">
        <img class="me-2" style="height: 40px; width: auto; max-height: 100%; object-fit: contain;" src="{{ site_image(siteUrlSettings('site_logo')) }}" alt="logo"/>
    </a>
@else
    <x-hsmart-logo height="38px" />
@endif
