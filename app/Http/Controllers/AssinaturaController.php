<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Illuminate\Support\Facades\Log;

class AssinaturaController extends Controller
{
    public function index()
    {
        $user        = auth()->user();
        $assinatura  = $user->subscription('default');

        return view('assinatura.index', [
            'assinado'   => $user->subscribed('default'),
            'assinatura' => $assinatura,
        ]);
    }

    public function checkout()
    {
        $priceId  = config('cashier.price_id');
        $trialDays = (int) config('cashier.trial_days', 7);

        $options = [
            'success_url' => route('assinatura.sucesso') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('assinatura.index'),
        ];

        if ($trialDays > 0) {
            $options['subscription_data'] = ['trial_period_days' => $trialDays];
        }

        $checkout = auth()->user()
            ->newSubscription('default', $priceId)
            ->checkout($options);

        return redirect($checkout->url);
    }

    public function sucesso(Request $request)
    {
        $user = auth()->user();

        // Sincroniza a assinatura localmente sem depender do webhook
        if (($sessionId = $request->query('session_id')) && ! $user->subscribed('default')) {
            $this->syncFromCheckout($user, $sessionId);
        }

        return view('assinatura.sucesso');
    }

    public function portal()
    {
        return auth()->user()->redirectToBillingPortal(route('assinatura.index'));
    }

    // -------------------------------------------------------------------------

    private function syncFromCheckout($user, string $sessionId): void
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve(
                $sessionId,
                ['expand' => ['subscription.items']]
            );

            if (! $session->subscription) {
                return;
            }

            if (! \in_array($session->payment_status, ['paid', 'no_payment_required'])) {
                return;
            }

            // Garante que o stripe_id do cliente está salvo no usuário
            if (! $user->stripe_id) {
                $user->forceFill(['stripe_id' => $session->customer])->save();
            }

            $stripeSub  = $session->subscription;
            $stripeItem = $stripeSub->items->data[0];

            // Cria/atualiza o registro de assinatura local
            $sub = $user->subscriptions()->updateOrCreate(
                ['stripe_id' => $stripeSub->id],
                [
                    'type'          => 'default',
                    'stripe_status' => $stripeSub->status,
                    'stripe_price'  => $stripeItem->price->id,
                    'quantity'      => $stripeItem->quantity ?? 1,
                    'trial_ends_at' => $stripeSub->trial_end
                        ? Carbon::createFromTimestamp($stripeSub->trial_end)
                        : null,
                    'ends_at'       => null,
                ]
            );

            // Cria/atualiza o item da assinatura
            $sub->items()->updateOrCreate(
                ['stripe_id' => $stripeItem->id],
                [
                    'stripe_product' => $stripeItem->price->product,
                    'stripe_price'   => $stripeItem->price->id,
                    'quantity'       => $stripeItem->quantity ?? 1,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Cashier checkout sync falhou: ' . $e->getMessage());
        }
    }
}
