<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getHeading(): string | Htmlable
    {
        return __('Sign in to WiFi Billing');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString(
            '<div style="font-size: 13px; color: #64748b; margin-top: 4px; text-align: center;">'
            . 'PPPoE Fiber &amp; Hotspot WiFi Client Portal'
            . '<div style="margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">'
            . '<a href="/recharge/voucher" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 11.5px; font-weight: 700; background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; text-decoration: none;">'
            . '<span>⚡</span> Buy Hotspot Voucher / Plan &rarr;'
            . '</a>'
            . '<a href="/captive-portal/preview" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; text-decoration: none;">'
            . '<span>📶</span> Captive Portal Preview &nearr;'
            . '</a>'
            . '</div>'
            . '</div>'
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username / Phone (PPPoE & Hotspot)')
            ->placeholder('Enter PPPoE username, phone (e.g. 07XXXXXXXX), or voucher')
            ->helperText('Use your PPPoE account username or registered mobile phone number.')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => trim($data['username'] ?? ''),
            'password' => $data['password'] ?? '',
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('Invalid credentials. Please verify your PPPoE or Hotspot username/phone and password.'),
        ]);
    }
}
