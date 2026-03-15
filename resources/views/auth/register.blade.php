<x-guest-layout>

    <h2 class="text-base font-semibold text-gray-900 mb-6">Criar conta</h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-xs font-medium text-gray-700 mb-1">Nome</label>
            <input id="name" type="text" name="name"
                   value="{{ old('name') }}"
                   required autofocus autocomplete="name"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-xs font-medium text-gray-700 mb-1">E-mail</label>
            <input id="email" type="email" name="email"
                   value="{{ old('email') }}"
                   required autocomplete="username"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Senha</label>
            <input id="password" type="password" name="password"
                   required autocomplete="new-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('password_confirmation')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 rounded-lg transition-colors">
            Criar conta
        </button>

        <p class="text-center text-xs text-gray-500 pt-2">
            Já tem uma conta?
            <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                Entrar
            </a>
        </p>
    </form>

</x-guest-layout>
