<?php

namespace App\Http\Requests;

use App\Services\CartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = Auth::guard('sanctum')->user();

        $rules = [
            'guest_first_name' => $user ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'guest_last_name' => $user ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'session_id' => ['nullable', 'string'],
            'cgv_accepted' => ['required', 'accepted'],
        ];

        $rules['guest_email'] = $user
            ? ['nullable', 'email']
            : ['required', 'email'];

        $requiresShipping = $this->cartRequiresShipping();

        $phoneRules = ['string', 'max:20', 'regex:/^0[1-9]\d{8}$/'];
        $addressLineRules = ['string', 'max:255'];
        $postalRules = ['string', 'regex:/^\d{5}$/'];
        $cityRules = ['string', 'max:100'];

        if ($requiresShipping) {
            $rules['shipping_phone'] = array_merge(['required'], $phoneRules);
            $rules['shipping_address_line1'] = array_merge(['required'], $addressLineRules);
            $rules['shipping_address_line2'] = array_merge(['nullable'], $addressLineRules);
            $rules['shipping_postal_code'] = array_merge(['required'], $postalRules);
            $rules['shipping_city'] = array_merge(['required'], $cityRules);
        } else {
            $rules['shipping_phone'] = array_merge(['nullable'], $phoneRules);
            $rules['shipping_address_line1'] = array_merge(['nullable'], $addressLineRules);
            $rules['shipping_address_line2'] = array_merge(['nullable'], $addressLineRules);
            $rules['shipping_postal_code'] = array_merge(['nullable'], $postalRules);
            $rules['shipping_city'] = array_merge(['nullable'], $cityRules);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'guest_first_name.required' => 'Le prénom est obligatoire.',
            'guest_last_name.required' => 'Le nom est obligatoire.',
            'cgv_accepted.required' => 'Vous devez accepter les Conditions Générales de Vente.',
            'cgv_accepted.accepted' => 'Vous devez accepter les Conditions Générales de Vente.',
            'shipping_phone.required' => 'Le numéro de téléphone est obligatoire pour l\'envoi des tirages.',
            'shipping_phone.regex' => 'Le numéro doit être un numéro français à 10 chiffres (ex: 0612345678).',
            'shipping_address_line1.required' => 'L\'adresse est obligatoire pour l\'envoi des tirages.',
            'shipping_postal_code.required' => 'Le code postal est obligatoire pour l\'envoi des tirages.',
            'shipping_postal_code.regex' => 'Le code postal doit contenir 5 chiffres.',
            'shipping_city.required' => 'La ville est obligatoire pour l\'envoi des tirages.',
        ];
    }

    /**
     * Vrai si le panier contient au moins un article nécessitant un envoi postal.
     * Les tirages scolaires (print_scolaire) sont remis à l'école et ne déclenchent ni
     * adresse ni frais.
     */
    private function cartRequiresShipping(): bool
    {
        $user = Auth::guard('sanctum')->user();
        $sessionId = $this->header('X-Cart-Session') ?? $this->input('session_id');

        try {
            $cart = app(CartService::class)->getOrCreateCart($user, $sessionId);
            $cart->loadMissing('items.photo.gallery.galleryProductTypes');

            return $cart->items->contains(function ($item) {
                $gallery = $item->photo?->gallery;
                $productType = $item->product_type ?? 'digital';

                return $gallery
                    ? $gallery->getRequiresShippingForProductType($productType)
                    : \App\Models\CartItem::requiresShipping($productType);
            });
        } catch (\Exception) {
            return false;
        }
    }
}
