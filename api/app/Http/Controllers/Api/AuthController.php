<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Gallery;
use App\Models\LoginAttempt;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // RGPD: Stocker les infos de consentement pour le User Observer
        $request->merge([
            'gdpr_consent_verified' => true,
            'consent_ip' => $request->ip(),
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'client',
        ]);

        // Rattacher l'historique existant par email au nouvel utilisateur
        Gallery::where('assigned_email', $validated['email'])
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        Order::where('guest_email', $validated['email'])
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        Reservation::where('guest_email', $validated['email'])
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {

        if (! Auth::attempt($request->only('email', 'password'))) {
            $this->journaliserTentative($request, false);

            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $this->journaliserTentative($request, true, $user);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Sous authentification par session, currentAccessToken() renvoie un
        // TransientToken : cibler ce seul token laisserait le jeton porteur actif.
        $request->user()?->tokens()->delete();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $request->validated();

        $status = Password::sendResetLink($request->only('email'));

        // Reponse identique quel que soit le statut : un 422 « aucun compte » permettait
        // d'enumerer les adresses inscrites, et un statut « throttled » revelait tout
        // autant que l'adresse existe.
        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Demande de réinitialisation non aboutie', ['status' => $status]);
        }

        return response()->json([
            'message' => 'Si un compte existe pour cette adresse, un lien de réinitialisation vient d\'être envoyé.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $request->validated();

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                // Sans cela, le jeton d'un compte compromis restait valable jusqu'a son
                // expiration : reinitialiser le mot de passe ne rendait pas l'acces.
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès.',
            ]);
        }

        // Message unique : distinguer « aucun compte » de « jeton invalide » permettait
        // d'enumerer les adresses inscrites avec un jeton au hasard.
        Log::warning('Réinitialisation de mot de passe refusée', ['status' => $status]);

        throw ValidationException::withMessages([
            'email' => ['Ce lien de réinitialisation est invalide ou a expiré.'],
        ]);
    }

    private function journaliserTentative(Request $request, bool $reussie, ?User $user = null): void
    {
        LoginAttempt::create([
            'email' => Str::lower((string) $request->input('email')),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'successful' => $reussie,
            'user_id' => $user?->id,
        ]);
    }
}
