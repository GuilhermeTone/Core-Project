<x-guest-layout>

    <h2 class="text-base font-semibold text-gray-900 mb-6">Entrar na conta</h2>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-medium text-gray-700 mb-1">E-mail</label>
            <input id="email" type="email" name="email"
                   value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Senha</label>
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600">
                <span class="text-xs text-gray-600">Lembrar de mim</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-xs text-blue-600 hover:text-blue-800 transition-colors">
                    Esqueci a senha
                </a>
            @endif
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 rounded-lg transition-colors">
            Entrar
        </button>

        @if (Route::has('register'))
            <p class="text-center text-xs text-gray-500 pt-2">
                Não tem uma conta?
                <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    Criar conta grátis
                </a>
            </p>
        @endif
    </form>

</x-guest-layout>
