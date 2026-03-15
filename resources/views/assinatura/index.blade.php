<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura — Busca de Ferramentas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<x-app-header>
    <h1 class="text-base font-semibold text-gray-800">Assinatura</h1>
</x-app-header>

<main class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">

        @if (session('warning'))
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 rounded-lg">
                {{ session('warning') }}
            </div>
        @endif

        @if ($assinado)
            {{-- Usuário já assina: mostra status e portal --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-1">Assinatura Ativa</h2>
                <p class="text-sm text-gray-500 mb-6">
                    Seu plano está ativo.
                    @if ($assinatura?->ends_at)
                        Cancela em {{ $assinatura->ends_at->format('d/m/Y') }}.
                    @endif
                </p>

                <div class="flex flex-col gap-3">
                    <a href="{{ route('ferramentas.index') }}"
                       class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition-colors text-sm">
                        Acessar Ferramentas
                    </a>
                    <form method="POST" action="{{ route('assinatura.portal') }}">
                        @csrf
                        <button type="submit"
                                class="w-full bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 font-medium py-2.5 rounded-lg transition-colors text-sm">
                            Gerenciar Assinatura
                        </button>
                    </form>
                </div>
            </div>
        @else
            {{-- Plano de assinatura --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-blue-600 px-8 py-6 text-center">
                    <h2 class="text-2xl font-bold text-white">Plano Pro</h2>
                    <p class="text-blue-100 text-sm mt-1">Acesso completo à plataforma</p>
                </div>

                <div class="px-8 py-6">
                    <div class="text-center mb-6">
                        @php $trial = (int) config('cashier.trial_days', 0); @endphp
                        @if ($trial > 0)
                            <div class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-3">
                                {{ $trial }} dias grátis
                            </div>
                        @endif
                        <div>
                            <span class="text-4xl font-bold text-gray-900">R$&nbsp;49</span>
                            <span class="text-gray-500 text-sm">/mês</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            @if ($trial > 0)
                                Grátis por {{ $trial }} dias, depois R$&nbsp;49/mês · Cancele quando quiser
                            @else
                                Cobrança recorrente · Cancele quando quiser
                            @endif
                        </p>
                    </div>

                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Pesquisa ilimitada em 9 lojas
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Orçamentos com margem de lucro
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Exportação de orçamento em PDF
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Dados isolados por usuário
                        </li>
                    </ul>

                    <form method="POST" action="{{ route('assinatura.checkout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">
                            Assinar agora
                        </button>
                    </form>

                    <p class="text-xs text-center text-gray-400 mt-4">
                        Pagamento seguro via Stripe · SSL
                    </p>
                </div>
            </div>
        @endif
    </div>
</main>

</body>
</html>
