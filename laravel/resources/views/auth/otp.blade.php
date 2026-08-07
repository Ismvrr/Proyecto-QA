<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación | C2D OnCloud Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-xl w-96">
        <h2 class="text-2xl font-bold mb-2 text-slate-800 text-center">Verificación 2FA</h2>
        <p class="text-sm text-slate-500 text-center mb-6">Ingresa el código temporal (Google Authenticator / Yandex Key).</p>
        
        @if($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>{{ $errors->first() }}</p>
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Correo de Chat2Desk</label>
                <input type="email" name="email" value="{{ $email ?? old('email') }}" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
                <input type="password" name="password" required autocomplete="current-password"
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2 text-center">Código de un solo uso (OTP)</label>
                <input type="text" name="otp" required autocomplete="off" autofocus
                    class="w-full px-3 py-3 text-center tracking-widest text-2xl border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
	    </div>
		<script src="https://www.google.com/recaptcha/api.js" async defer></script>

            @if(config('services.recaptcha.site_key'))
                <div class="mb-4 flex justify-center">
                    <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                </div>
            @endif

            <button type="submit" 
                class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
                Verificar y Entrar
            </button>
            <a href="{{ route('login') }}" class="block text-center mt-4 text-sm text-blue-500 hover:underline">Cancelar y volver al inicio</a>
        </form>
    </div>
</body>
</html>
