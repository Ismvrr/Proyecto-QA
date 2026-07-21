<?php

/**
 * Chat2Desk Login Controller - API_C2D
 *
 * Controlador de autenticación que conecta con Chat2Desk.
 *
 * Flujo de login:
 *   1. Usuario ingresa email/password en el formulario
 *   2. Se envía a la API de Chat2Desk (sign_in)
 *   3. Si requiere OTP → mostrar formulario OTP
 *   4. Si es exitoso → crear/actualizar usuario local
 *   5. Generar JWT para FastAPI
 *   6. Redirigir a dashboard con cookie JWT
 *
 * JWT:
 *   - Se genera con firebase/php-jwt
 *   - Se guarda en cookie httpOnly, secure
 *   - Expiración: 8 horas
 *   - FastAPI valida este token en cada petición API
 *
 * Seguridad:
 *   - Cookie: httpOnly (no accesible por JS), secure (solo HTTPS)
 *   - Dominio: .chat2desk.support (compartido entre Laravel y FastAPI)
 *   - JWT_SECRET compartido entre ambos servicios
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Chat2DeskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Chat2DeskLoginController extends Controller
{
    protected $c2dService;

    public function __construct(Chat2DeskService $c2dService)
    {
        $this->c2dService = $c2dService;
    }

    /**
     * Genera un token JWT para consumo de FastAPI.
     *
     * El token contiene:
     *   - sub: Email del usuario (identificador principal)
     *   - user_id: ID en la base de datos local
     *   - company_id: ID de la empresa (multi-tenant)
     *   - role: Rol del usuario (admin, user, shadow)
     *   - iat: Timestamp de creación
     *   - exp: Timestamp de expiración (8 horas)
     *
     * @param User $user Modelo de usuario de Laravel
     * @return string Token JWT codificado
     */
    private function generateJwtToken(User $user): string
    {
        $secret = config('services.jwt_secret', env('JWT_SECRET'));
        $payload = [
            'sub' => $user->email,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'role' => $user->role,
            'iat' => now()->timestamp,
            'exp' => now()->addHours(8)->timestamp,
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Muestra el formulario de login.
     *
     * @return \Illuminate\View\View Vista auth.login
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Procesa el intento de login contra Chat2Desk.
     *
     * Flujo completo:
     *   1. Validar input (email, password)
     *   2. Llamar a Chat2Desk API (sign_in)
     *   3. Manejar respuestas:
     *      - OTP requerido → mostrar formulario OTP
     *      - Error → mostrar mensaje de error
     *      - Éxito → crear usuario + JWT + redirect
     *
     * @param Request $request Request HTTP con credenciales
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function login(Request $request)
    {
        // Validación de entrada
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $otp = $request->input('otp');
        $recaptchaResponse = $request->input('g-recaptcha-response');
        
        // Llamar a la API de Chat2Desk
        $c2dResponse = $this->c2dService->signIn(
            $request->email, 
            $request->password, 
            $otp, 
            $recaptchaResponse
        );

        if (!$c2dResponse) {
            return back()->withErrors(['email' => 'Error de comunicaci\u00f3n con Chat2Desk.']);
        }
       
        // Manejar errores de Chat2Desk
        if (isset($c2dResponse['status']) && $c2dResponse['status'] === 'error') {
            $errors = $c2dResponse['errors']['error'] ?? [];

            // OTP requerido o incorrecto → mostrar formulario OTP
            if (in_array('incorrect_otp', $errors) || isset($c2dResponse['errors']['otp_required'])) {
                $request->session()->put('temp_c2d_email', $request->email);
                $request->session()->put('temp_c2d_password', $request->password);
                return view('auth.otp');
            }

            // Otro error → mostrar respuesta cruda
            $mensajeCrudo = json_encode($c2dResponse);
            return back()->withErrors(['email' => "Respuesta de C2D: " . $mensajeCrudo]);
        }

        // Login exitoso → extraer auth_key
        $authKey = $c2dResponse['data']['auth_key'] ?? $c2dResponse['auth_key'] ?? null;

        if (!$authKey) {
            return back()->withErrors(['email' => 'No se pudo obtener la llave de autorizaci\u00f3n (auth_key).']);
        }

        // Limpiar datos temporales de sesión
        $request->session()->forget(['temp_c2d_email', 'temp_c2d_password']);

        // Buscar o crear usuario local
        $user = User::where('email', $request->email)->where('isdeleted', 0)->first();

        if (!$user) {
            // Primer login → crear usuario con rol 'shadow'
            $user = User::create([
                'email' => $request->email,
                'auth_key' => $authKey,
                'status' => 'enabled',
                'role' => 'shadow', 
            ]);
        } else {
            // Login existente → actualizar auth_key
            $user->update(['auth_key' => $authKey]);
        }

        // Autenticar en Laravel
        Auth::login($user);

        // Generar JWT para FastAPI
        $token = $this->generateJwtToken($user);

        // Redirigir a dashboard con JWT en cookie
        $response = redirect()->intended('/dashboard');
        $response->cookie(
            'token',           // Nombre de la cookie
            $token,            // Valor: JWT
            480,               // Expiración: 8 horas (en minutos)
            '/',               // Path: raíz
            '.chat2desk.support',  // Dominio: compartido entre servicios
            true,              // Secure: solo HTTPS
            true               // httpOnly: no accesible por JavaScript
        );

        return $response;
    }

    /**
     * Cierra la sesión del usuario.
     *
     * Limpia:
     *   - Sesión de Laravel (Auth::logout())
     *   - Cookie JWT (token)
     *   - Redirige a /login
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        Auth::logout();
        $response = redirect('/login');
        $response->forgetCookie('token');
        return $response;
    }
}
