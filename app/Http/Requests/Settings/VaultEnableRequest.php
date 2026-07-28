<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class VaultEnableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Every field here is opaque to the server: ciphertexts it cannot open, salts,
     * and a hash. Nothing that could reconstruct the passphrase or the key.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wrapped_passphrase' => ['required', 'string', 'max:4096'],
            'wrapped_recovery' => ['required', 'string', 'max:4096'],
            'kdf' => ['required', 'string', 'in:pbkdf2-sha256'],
            // Floored well below the app default so a hostile client cannot wrap
            // the key behind a trivially brute-forceable derivation.
            'kdf_iterations' => ['required', 'integer', 'min:100000', 'max:5000000'],
            'kdf_salt' => ['required', 'string', 'max:64'],
            'recovery_salt' => ['required', 'string', 'max:64'],
            'fingerprint' => ['required', 'string', 'size:64'],
            'acknowledged_recovery_key' => ['required', 'accepted'],
        ];
    }
}
