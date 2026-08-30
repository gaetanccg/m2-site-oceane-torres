<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur || ! $utilisateur->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // ⚖️ Une garde supplementaire sur la capacite du jeton — `tokenCan('admin')` — a ete
        // essayee puis retiree le 30/08. Ce qu'elle empeche : un jeton emis quand le compte
        // etait cliente, reutilise apres promotion en admin. Sur un site a 18 comptes et une
        // seule administratrice, ce cas n'existe pratiquement pas. Ce qu'elle coute : une
        // seconde condition d'echec permanente sur chaque requete d'administration, dont la
        // panne ressemble a un bug de droits, et 25 tests a reecrire pour modeliser un jeton
        // que la production n'emet jamais.
        //
        // Les jetons portent malgre tout leur capacite de role (`User::jetonDeSession()`) :
        // c'est gratuit, plus juste qu'un `*` tout-puissant, et cela rendra la garde
        // immediate le jour ou des jetons restreints existeront — application mobile, codes
        // de recuperation du second facteur. **Condition de levee : ce jour-la.**

        return $next($request);
    }
}
