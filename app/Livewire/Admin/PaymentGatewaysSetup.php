<?php

namespace App\Livewire\Admin;

use App\Models\MainSiteData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;

class PaymentGatewaysSetup extends Component
{
    // SMS Forwarder
    public bool $payment_mpesa_sms_enabled = false;
    public string $sms_receiver_token = '';

    // Daraja
    public bool $payment_daraja_enabled = false;
    public string $daraja_env = 'sandbox';
    public string $daraja_shortcode = '';
    public string $daraja_consumer_key = '';
    public string $daraja_consumer_secret = '';
    public string $daraja_passkey = '';

    // PayHero
    public bool $payment_payhero_enabled = false;
    public string $payhero_base_url = 'https://backend.payhero.co.ke/api/v2';
    public string $payhero_api_key = '';
    public string $payhero_api_secret = '';
    public string $payhero_channel_id = '';

    // KopoKopo
    public bool $payment_kopokopo_enabled = false;
    public string $kopokopo_env = 'sandbox';
    public string $kopokopo_client_id = '';
    public string $kopokopo_client_secret = '';
    public string $kopokopo_till_number = '';
    public string $kopokopo_webhook_secret = '';

    public string $statusMessage = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['site-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        // SMS Forwarder
        $this->payment_mpesa_sms_enabled = (bool) MainSiteData::getValue('payment_mpesa_sms_enabled', false);
        $this->sms_receiver_token        = (string) (MainSiteData::getValue('sms_receiver_token') ?: env('SMS_RECEIVER_TOKEN', ''));

        // Daraja
        $this->payment_daraja_enabled = (bool) MainSiteData::getValue('payment_daraja_enabled', false);
        $this->daraja_env             = (string) MainSiteData::getValue('daraja_env', 'sandbox');
        $this->daraja_shortcode       = (string) $this->safeDecrypt(MainSiteData::getValue('daraja_shortcode') ?: env('MPESA_SHORTCODE', ''));
        $this->daraja_consumer_key    = (string) $this->safeDecrypt(MainSiteData::getValue('daraja_consumer_key') ?: env('MPESA_CONSUMER_KEY', ''));
        $this->daraja_consumer_secret = (string) $this->safeDecrypt(MainSiteData::getValue('daraja_consumer_secret') ?: env('MPESA_CONSUMER_SECRET', ''));
        $this->daraja_passkey         = (string) $this->safeDecrypt(MainSiteData::getValue('daraja_passkey') ?: env('MPESA_PASSKEY', ''));

        // PayHero
        $this->payment_payhero_enabled = (bool) MainSiteData::getValue('payment_payhero_enabled', false);
        $this->payhero_base_url        = (string) MainSiteData::getValue('payhero_base_url', 'https://backend.payhero.co.ke/api/v2');
        $this->payhero_api_key         = (string) $this->safeDecrypt(MainSiteData::getValue('payhero_api_key') ?: env('PAYHERO_API_KEY', ''));
        $this->payhero_api_secret      = (string) $this->safeDecrypt(MainSiteData::getValue('payhero_api_secret') ?: env('PAYHERO_API_SECRET', ''));
        $this->payhero_channel_id      = (string) $this->safeDecrypt(MainSiteData::getValue('payhero_channel_id') ?: env('PAYHERO_CHANNEL_ID', ''));

        // KopoKopo
        $this->payment_kopokopo_enabled = (bool) MainSiteData::getValue('payment_kopokopo_enabled', false);
        $this->kopokopo_env             = (string) MainSiteData::getValue('kopokopo_env', 'sandbox');
        $this->kopokopo_client_id       = (string) $this->safeDecrypt(MainSiteData::getValue('kopokopo_client_id') ?: env('KOPOKOPO_CLIENT_ID', ''));
        $this->kopokopo_client_secret   = (string) $this->safeDecrypt(MainSiteData::getValue('kopokopo_client_secret') ?: env('KOPOKOPO_CLIENT_SECRET', ''));
        $this->kopokopo_till_number     = (string) $this->safeDecrypt(MainSiteData::getValue('kopokopo_till_number') ?: env('KOPOKOPO_TILL_NUMBER', ''));
        $this->kopokopo_webhook_secret  = (string) $this->safeDecrypt(MainSiteData::getValue('kopokopo_webhook_secret') ?: env('KOPOKOPO_WEBHOOK_SECRET', ''));
    }

    protected function safeDecrypt(?string $value): string
    {
        if (empty($value)) return '';
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    protected function safeEncrypt(?string $value): ?string
    {
        if (empty(trim((string)$value))) return null;
        return Crypt::encryptString(trim((string)$value));
    }

    public function save(): void
    {
        if (! hasAccess(['Super Admin'], ['site-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        // SMS Forwarder
        MainSiteData::setValue('payment_mpesa_sms_enabled', $this->payment_mpesa_sms_enabled);
        MainSiteData::setValue('sms_receiver_token', trim($this->sms_receiver_token));

        // Daraja
        MainSiteData::setValue('payment_daraja_enabled', $this->payment_daraja_enabled);
        MainSiteData::setValue('daraja_env', $this->daraja_env);
        MainSiteData::setValue('daraja_shortcode', $this->safeEncrypt($this->daraja_shortcode));
        MainSiteData::setValue('daraja_consumer_key', $this->safeEncrypt($this->daraja_consumer_key));
        MainSiteData::setValue('daraja_consumer_secret', $this->safeEncrypt($this->daraja_consumer_secret));
        MainSiteData::setValue('daraja_passkey', $this->safeEncrypt($this->daraja_passkey));

        // PayHero
        MainSiteData::setValue('payment_payhero_enabled', $this->payment_payhero_enabled);
        MainSiteData::setValue('payhero_base_url', rtrim($this->payhero_base_url, '/'));
        MainSiteData::setValue('payhero_api_key', $this->safeEncrypt($this->payhero_api_key));
        MainSiteData::setValue('payhero_api_secret', $this->safeEncrypt($this->payhero_api_secret));
        MainSiteData::setValue('payhero_channel_id', $this->safeEncrypt($this->payhero_channel_id));

        // KopoKopo
        MainSiteData::setValue('payment_kopokopo_enabled', $this->payment_kopokopo_enabled);
        MainSiteData::setValue('kopokopo_env', $this->kopokopo_env);
        MainSiteData::setValue('kopokopo_client_id', $this->safeEncrypt($this->kopokopo_client_id));
        MainSiteData::setValue('kopokopo_client_secret', $this->safeEncrypt($this->kopokopo_client_secret));
        MainSiteData::setValue('kopokopo_till_number', $this->safeEncrypt($this->kopokopo_till_number));
        MainSiteData::setValue('kopokopo_webhook_secret', $this->safeEncrypt($this->kopokopo_webhook_secret));

        // Clear site settings cache
        Cache::flush();

        $this->statusMessage = 'Kenyan Payment Gateways settings saved and credentials securely encrypted successfully!';
    }

    public function render()
    {
        return view('livewire.admin.payment-gateways-setup')
            ->layout('layouts.app');
    }
}

