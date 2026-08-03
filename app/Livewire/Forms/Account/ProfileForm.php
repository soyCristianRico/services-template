<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Account;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The account of whoever is logged in. It never takes a user as input: it works
 * on the authenticated one, so no request can aim it at somebody else's row.
 */
class ProfileForm extends Form
{
    public string $name = '';

    public string $email = '';

    public function setUser(User $user): void
    {
        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore(auth()->id()),
            ],
        ];
    }

    public function save(): User
    {
        $this->validate();

        $user = auth()->user();

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        return $user;
    }
}
