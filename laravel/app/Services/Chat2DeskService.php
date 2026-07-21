<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Chat2DeskService
{
    protected string $baseUrl;

    public function __construct()
    {
        // Usamos la URL que definimos en el .env
        $this->baseUrl = config('services.chat2desk.api_url');
    }

    /**
     * Realiza el login (Nivel 1) para obtener el auth_key de Chat2Desk
     */
    public function signIn(string $email, string $password, ?string $otp = null, ?string $recaptcha = null)
    {
        try {
            // Usamos la URL base web que definiste en config/services.php
            $webUrl = config('services.chat2desk.web_url');
            $endpoint = "{$webUrl}/api/user/sign_in?lang=es";

            $payload = [
                'email'    => $email,
                'password' => $password,
            ];

            if ($otp) {
                $payload['oneTimePassword'] = $otp;
            }

            if ($recaptcha) {
                $payload['grecaptchaResponse'] = $recaptcha;
            }

            $response = Http::post($endpoint, $payload);
            return $response->json();

        } catch (\Exception $e) {
            Log::error("Error de conexión con API Chat2Desk Web: " . $e->getMessage());
            return null;
        }
    }
}
