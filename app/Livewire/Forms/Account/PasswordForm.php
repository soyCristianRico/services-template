<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Account;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Livewire\Form;

/**
 * The password of whoever is logged in. The strength rules are Fortify's own,
 * so this screen and the reset-by-email flow always ask for the same thing.
 *
 * The property names are snake_case because `confirmed` looks for a sibling
 * called `password_confirmation`, spelled exactly like that.
 */
class PasswordForm extends Form
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        auth()->user()->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        $this->reset();
    }
}
