<?php

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Codepagol\SmsBridge\Facades\SmsBridge;
use Codepagol\SmsBridge\Response\SmsResponse;

class SMSController extends Controller
{
    public function allCustomersSMS(array $data)
    {
        $template = SmsTemplate::where('template_name', 'all_customers')->first();

        if (! $template) {
            return new SmsResponse(false, 'Template not found');
        }

        if ($template->is_active != 1) {
            return new SmsResponse(false, 'Template is disabled');
        }

        $message = str_replace(
            ['{CUSTOMER_NAME}', '{MONTH}', '{BILL_AMOUNT}', '{CUSTOMER_ID}', '{IP_OR_USER_NAME_OR_ID}', '{LAST_DAY_OF_PAY_BILL}', '{COMPANY_NAME}', '{COMPANY_MOBILE}'],
            [$data['customer_name'], $data['month'], $data['bill_amount'], $data['customer_id'], $data['ip_or_user_name'], $data['last_day_of_pay_bill'], $data['company_name'], $data['company_mobile']],
            $template->template
        );

        return SmsBridge::to($data['recipient'])
            ->message($message)
            ->send();
    }

    public function paymentCollectionSMS(array $data)
    {
        $template = SmsTemplate::where('template_name', 'payment_collection')->first();

        if (! $template) {
            return new SmsResponse(false, 'Template not found');
        }

        if ($template->is_active != 1) {
            return new SmsResponse(false, 'Template is disabled');
        }

        $balanceText = $data['due_amount'] < 0
            ? 'Advance: '.abs($data['due_amount'])
            : 'Due: '.$data['due_amount'];

        $message = str_replace(
            ['{CUSTOMER_NAME}', '{AMOUNT}', '{IP_OR_USER_NAME_OR_ID}', '{BALANCE}', '{COMPANY_NAME}'],
            [$data['customer_name'], $data['collection_amount'], $data['ip_or_user_name'], $balanceText, $data['company_name']],
            $template->template
        );

        return SmsBridge::to($data['recipient'])
            ->message($message)
            ->send();
    }

    public function collectionDeleteSMS(array $data)
    {
        $template = SmsTemplate::where('template_name', 'collection_delete')->first();

        if (! $template) {
            return new SmsResponse(false, 'Template not found');
        }

        if ($template->is_active != 1) {
            return new SmsResponse(false, 'Template is disabled');
        }

        $balanceText = $data['due_amount'] < 0
            ? 'Advance: '.abs($data['due_amount']).'/='
            : 'Due: '.$data['due_amount'].'/=';

        $message = str_replace(
            ['{CUSTOMER_NAME}', '{AMOUNT}', '{IP_OR_USER_NAME_OR_ID}', '{TOTAL_COLLECTION}', '{COMPANY_MOBILE}'],
            [$data['customer_name'], $data['collection_amount'], $data['ip_or_user_name'], $balanceText, $data['company_mobile']],
            $template->template
        );

        return SmsBridge::to($data['recipient'])
            ->message($message)
            ->send();
    }

    public function billReminderSMS(array $data)
    {
        $template = SmsTemplate::where('template_name', 'area_wise_customer_due_list')->first()
            ?: SmsTemplate::where('template_name', 'all_customers')->first();

        $companyName = $data['company_name'] ?? (siteUrlSettings('site_name') ?: config('app.name'));
        $companyMobile = $data['company_mobile'] ?? (siteUrlSettings('site_phone') ?: '');

        if ($template && $template->is_active) {
            $message = str_replace(
                ['{CUSTOMER_NAME}', '{AMOUNT}', '{CUSTOMER_ID}', '{LAST_DAY_DUE_DATE}', '{AUTO_TEMPORARY_DAY}', '{COMPANY_NAME}', '{COMPANY_MOBILE}'],
                [
                    $data['customer_name'] ?? 'Customer',
                    $data['due_amount'] ?? 0,
                    $data['customer_id'] ?? '',
                    $data['due_date'] ?? $data['auto_temporary_day'] ?? 'soon',
                    $data['auto_temporary_day'] ?? $data['due_date'] ?? 'soon',
                    $companyName,
                    $companyMobile,
                ],
                $template->template
            );
        } else {
            $message = "Dear {$data['customer_name']}, your Internet bill of KES {$data['due_amount']} is due on {$data['due_date']}. Please pay to avoid disconnection. - {$companyName}";
        }

        $recipient = normalizeKenyanPhone($data['recipient']) ?? $data['recipient'];

        return SmsBridge::to($recipient)
            ->message($message)
            ->send();
    }

    public function expiryNoticeSMS(array $data)
    {
        $template = SmsTemplate::where('template_name', 'auto_temporary_disable_alert')->first();

        $companyName = $data['company_name'] ?? (siteUrlSettings('site_name') ?: config('app.name'));
        $companyMobile = $data['company_mobile'] ?? (siteUrlSettings('site_phone') ?: '');

        if ($template && $template->is_active) {
            $message = str_replace(
                ['{CUSTOMER_NAME}', '{CUSTOMER_ID}', '{DUE_AMOUNT}', '{COMPANY_NAME}', '{COMPANY_MOBILE}'],
                [
                    $data['customer_name'] ?? 'Customer',
                    $data['customer_id'] ?? '',
                    $data['due_amount'] ?? 0,
                    $companyName,
                    $companyMobile,
                ],
                $template->template
            );
        } else {
            $message = "Dear {$data['customer_name']}, ID {$data['customer_id']} has expired and is disconnected. Due: KES {$data['due_amount']}. Please pay to restore service. - {$companyName}";
        }

        $recipient = normalizeKenyanPhone($data['recipient']) ?? $data['recipient'];

        return SmsBridge::to($recipient)
            ->message($message)
            ->send();
    }
}
