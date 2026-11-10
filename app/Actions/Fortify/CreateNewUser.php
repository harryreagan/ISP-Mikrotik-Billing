<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'role' => 'admin',
                'status' => 'active',
                'is_super_admin' => 0,
            ]);

            // Assign Admin role
            try {
                if (\Spatie\Permission\Models\Role::where('name', 'Admin')->exists()) {
                    $user->assignRole('Admin');
                }
            } catch (\Throwable $e) {
                // Ignore role assignment failure if table not ready
            }

            // Create dedicated Tenant for this ISP Admin if none assigned
            if (empty($user->tenant_id)) {
                try {
                    $baseSlug = \Illuminate\Support\Str::slug($user->name);
                    $subdomain = $baseSlug . '-' . $user->id;
                    $billingCode = strtoupper(\Illuminate\Support\Str::random(6));

                    $tenantId = DB::table('tenants')->insertGetId([
                        'name' => $user->name . ' Networks',
                        'subdomain' => $subdomain,
                        'billing_code' => $billingCode,
                        'country' => 'Kenya',
                        'county' => 'Nairobi',
                        'is_active' => 1,
                        'sms_balance' => 20.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Award 20 free SMS credits in sms_wallets on onboarding
                    try {
                        DB::table('sms_wallets')->insert([
                            'tenant_id'             => $tenantId,
                            'balance'               => 20.00,
                            'low_balance_threshold' => 50.00,
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    $user->tenant_id = $tenantId;
                    $user->save();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Auto-create tenant failed for user {$user->id}: " . $e->getMessage());
                }
            }

            $this->createTeam($user);

            return $user;
        });
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): void
    {
        $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));
    }
}
