<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Meu Perfil — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<x-app-header>
    <div class="flex items-center gap-3 min-w-0">
        <a href="{{ route('planilhas.index') }}"
           class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-sm font-semibold text-gray-800 leading-tight">Meu Perfil</h1>
            <p class="text-xs text-gray-400 hidden sm:block">{{ auth()->user()->email }}</p>
        </div>
    </div>
</x-app-header>

<main class="max-w-xl mx-auto px-4 py-8 space-y-5" x-data="{ confirmDelete: false }">

    {{-- Mensagens de sucesso --}}
    @if (session('status') === 'profile-updated')
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
            Perfil atualizado com sucesso.
        </div>
    @endif
    @if (session('status') === 'password-updated')
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
            Senha atualizada com sucesso.
        </div>
    @endif

    {{-- Informações do perfil --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Informações do perfil</h2>
        <p class="text-xs text-gray-500 mb-5">Atualize seu nome e endereço de e-mail.</p>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 mb-1">Nome</label>
                <input id="name" name="name" type="text"
                       value="{{ old('name', auth()->user()->name) }}"
                       required autofocus autocomplete="name"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 mb-1">E-mail</label>
                <input id="email" name="email" type="email"
                       value="{{ old('email', auth()->user()->email) }}"
                       required autocomplete="username"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Salvar
                </button>
            </div>
        </form>
    </div>

    {{-- Alterar senha --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Alterar senha</h2>
        <p class="text-xs text-gray-500 mb-5">Use uma senha forte para proteger sua conta.</p>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-xs font-medium text-gray-700 mb-1">Senha atual</label>
                <input id="current_password" name="current_password" type="password"
                       autocomplete="current-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('current_password', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Nova senha</label>
                <input id="password" name="password" type="password"
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('password', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">Confirmar nova senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Atualizar senha
                </button>
            </div>
        </form>
    </div>

    {{-- Excluir conta --}}
    <div class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
        <h2 class="text-sm font-semibold text-red-700 mb-1">Excluir conta</h2>
        <p class="text-xs text-gray-500 mb-5">
            Ao excluir sua conta, todos os dados serão permanentemente removidos.
        </p>

        <button @click="confirmDelete = true"
                class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
            Excluir minha conta
        </button>
    </div>

    {{-- Modal de confirmação de exclusão --}}
    <div x-show="confirmDelete"
         x-transition.opacity
         class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4"
         style="display: none">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" @click.outside="confirmDelete = false">
            <h3 class="text-base font-semibold text-gray-900 mb-2">Confirmar exclusão</h3>
            <p class="text-sm text-gray-500 mb-5">
                Esta ação é irreversível. Todos os seus orçamentos e buscas serão excluídos. Digite sua senha para confirmar.
            </p>

            <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4">
                @csrf
                @method('DELETE')

                <div>
                    <label for="delete_password" class="block text-xs font-medium text-gray-700 mb-1">Senha</label>
                    <input id="delete_password" name="password" type="password"
                           placeholder="Digite sua senha"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    @error('password', 'userDeletion')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" @click="confirmDelete = false"
                            class="text-sm text-gray-600 hover:text-gray-800 border border-gray-200 px-4 py-2 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Excluir conta
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Abre modal se houver erro de deleção --}}
    @if ($errors->userDeletion->isNotEmpty())
        <script>
            document.addEventListener('alpine:init', () => {
                setTimeout(() => {
                    document.querySelector('[x-data]').__x.$data.confirmDelete = true;
                }, 100);
            });
        </script>
    @endif

</main>

</body>
</html>
