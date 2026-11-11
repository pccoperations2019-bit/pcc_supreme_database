<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatusEnum;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $loginField = $this->input('email');

        // Check if the login field is an email or username
        $authField = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials[$authField] = $loginField;
        if ($authField === 'username') {
            unset($credentials['email']);
        }

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // The password was correct, but the account may still be pending
        // approval, rejected, or deactivated. Sign it straight back out rather
        // than letting the session through.
        $this->ensureAccountIsActive();

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Refuse an account that exists and whose password matched but whose status
     * does not permit sign-in.
     *
     * The throttle is hit here too, so the distinct messages below cannot be
     * used to enumerate which usernames exist.
     *
     * @throws ValidationException
     */
    private function ensureAccountIsActive(): void
    {
        $user = Auth::user();

        if ($user === null || $user->canAuthenticate()) {
            return;
        }

        Auth::logout();
        RateLimiter::hit($this->throttleKey());

        $message = match ($user->status?->value) {
            UserStatusEnum::PENDING->value => 'Your account is awaiting approval. Please contact the PCC administrator.',
            UserStatusEnum::REJECTED->value => 'Your account was not approved. Please contact the PCC administrator.',
            default => 'Your account has been deactivated. Please contact the PCC administrator.',
        };

        throw ValidationException::withMessages(['email' => $message]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $loginField = $this->input('email');

        return Str::transliterate(Str::lower($loginField).'|'.$this->ip());
    }
}
