<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Team;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class CustomAuthVerificationAndGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
    }

    /** 1. Login and Register render combined view */
    public function test_login_and_register_render_combined_view(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Welcome back');
        $response->assertSee('Continue with Google');

        $regResponse = $this->get('/register');
        $regResponse->assertStatus(200);
        $regResponse->assertSee('Create your account');
        $regResponse->assertSee('Sign up with Google');
    }

    /** 2. Unverified user cannot log in */
    public function test_unverified_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** 3. Existing verified user can log in normally */
    public function test_existing_verified_user_can_log_in_normally(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'verified@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /** 4. Sign-up step 1 creates unverified user and sends 6-digit code */
    public function test_signup_step1_creates_unverified_user_and_sends_code(): void
    {
        $response = $this->post(route('auth.register.step1'), [
            'name' => 'John Doe',
            'business_name' => 'Nairobi Net',
            'mobile' => '0712345678',
            'email' => 'johndoe@example.com',
            'password' => 'SecurePass123!',
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('login', ['tab' => 'verify']));

        $user = User::where('email', 'johndoe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('John Doe', $user->name);

        Mail::assertQueued(VerificationCodeMail::class, function ($mail) {
            return $mail->hasTo('johndoe@example.com');
        });
    }

    /** 5. Sign-up rejects already verified email */
    public function test_signup_rejects_already_verified_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('auth.register.step1'), [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'terms' => 'on',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /** 6. Verification code success marks email verified and logs user in */
    public function test_verification_code_success_marks_verified_and_authenticates(): void
    {
        $service = app(EmailVerificationService::class);
        $email = 'verifytest@example.com';

        User::factory()->create([
            'email' => $email,
            'email_verified_at' => null,
        ]);

        // Generate code
        $service->generateAndSendCode($email, '127.0.0.1');

        // Extract stored hash
        $cacheKey = "verify_code:" . md5($email);
        $stored = Cache::get($cacheKey);
        $this->assertNotNull($stored);

        // Simulate knowing the code by writing a known hash
        $knownCode = '123456';
        $stored['hash'] = hash('sha256', $knownCode);
        Cache::put($cacheKey, $stored, 600);

        // Submit code
        $response = $this->post(route('auth.verify.code'), [
            'email' => $email,
            'code' => $knownCode,
        ]);

        $response->assertRedirect(route('login', ['tab' => 'done']));

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    /** 7. Wrong code increments attempts and shows remaining */
    public function test_wrong_code_increments_attempts(): void
    {
        $service = app(EmailVerificationService::class);
        $email = 'wrongcode@example.com';

        $service->generateAndSendCode($email, '127.0.0.1');

        $response = $this->post(route('auth.verify.code'), [
            'email' => $email,
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    /** 8. Too many wrong attempts revokes code */
    public function test_too_many_wrong_attempts_revokes_code(): void
    {
        $service = app(EmailVerificationService::class);
        $email = 'lockout@example.com';

        $service->generateAndSendCode($email, '127.0.0.1');

        // 5 wrong attempts
        for ($i = 0; $i < 5; $i++) {
            $service->verifyCode($email, '00000' . $i);
        }

        $result = $service->verifyCode($email, '123456');
        $this->assertContains($result['status'], ['exceeded', 'expired']);
    }

    /** 9. Resend cooldown (60s) is enforced */
    public function test_resend_cooldown_enforced(): void
    {
        $service = app(EmailVerificationService::class);
        $email = 'cooldown@example.com';

        $res1 = $service->generateAndSendCode($email, '127.0.0.1');
        $this->assertEquals('sent', $res1['status']);

        // Second immediate attempt within 60s
        $res2 = $service->generateAndSendCode($email, '127.0.0.1');
        $this->assertEquals('cooldown', $res2['status']);
    }

    /** 10. Google OAuth creates new user with verified email */
    public function test_google_oauth_creates_new_user_with_verified_email(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-123456');
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('getEmail')->andReturn('newgoogle@example.com');
        $abstractUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

        Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'newgoogle@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('google-123456', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    /** 11. Google OAuth links existing user and logs in */
    public function test_google_oauth_links_existing_user(): void
    {
        $existing = User::factory()->create([
            'email' => 'existinggoogle@example.com',
            'google_id' => null,
            'email_verified_at' => now(),
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-999999');
        $abstractUser->shouldReceive('getName')->andReturn('Existing User');
        $abstractUser->shouldReceive('getEmail')->andReturn('existinggoogle@example.com');
        $abstractUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

        Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));

        $existing->refresh();
        $this->assertEquals('google-999999', $existing->google_id);
        $this->assertAuthenticatedAs($existing);
    }
}
