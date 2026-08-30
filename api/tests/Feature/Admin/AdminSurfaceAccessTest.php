<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\EventCategory;
use App\Models\Gallery;
use App\Models\GiftCard;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Prestation;
use App\Models\PrivacyExport;
use App\Models\Reservation;
use App\Models\SchoolSession;
use App\Models\SchoolSessionExport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le filet avant D2. Deplacer 34 declarations de routes entre controleurs sans lui, c'est
 * reprendre la manoeuvre qui a produit deux escalades de privilege en 2.4 et 2.5.
 *
 * Sa propriete tient a ce qu'il n'enumere RIEN a la main : la liste vient du routeur. Une
 * route ajoutee demain est couverte le jour meme, et une route qui sort du groupe protege
 * fait tomber la suite. « Le middleware est sur le groupe » devient une propriete verifiee,
 * au lieu d'une intention.
 *
 * Les acteurs :
 *   - anonyme  → 401 sur TOUTES les routes ;
 *   - cliente  → 403 sur TOUTES les routes ;
 *   - admin    → ni 401 ni 403, sur les LECTURES seulement.
 *
 * Pourquoi l'admin n'exerce que les lectures : sur les 96 routes, 38 n'ont aucun parametre.
 * Un POST ou un DELETE joue alors reellement, et un test qui ecrit pour verifier un droit
 * d'acces est un test qui finira par casser des donnees. Les deux premiers acteurs, eux,
 * sont refuses par le middleware AVANT que le controleur ne s'execute : aucun risque.
 */
class AdminSurfaceAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Un identifiant valide en forme mais absent : le route-model-binding rend 404. */
    private const ID_INEXISTANT = '00000000-0000-4000-8000-000000000000';

    /**
     * `NotificationController` vit dans `Api\Admin\` mais filtre sur l'utilisateur courant :
     * il sert aussi les comptes clients. Seule exception legitime, et elle est nommee ici
     * pour qu'une seconde exige une decision plutot qu'un ajout silencieux.
     */
    private const HORS_PERIMETRE = ['App\Http\Controllers\Api\Admin\NotificationController'];

    /**
     * La surface qui DOIT etre reservee a l'administratrice.
     *
     * ⚠️ Elle ne s'enumere surtout pas par la presence du middleware. Une premiere version le
     * faisait, et un controle de mutation l'a prise en defaut : sortir une route du groupe la
     * retirait aussi de l'enumeration, donc le test passait toujours. Un filet qui disparait
     * avec ce qu'il doit attraper ne sert a rien.
     *
     * Le perimetre est donc defini par ce que la route EST — son prefixe d'URL ou son
     * controleur — et jamais par la protection qu'on veut lui verifier.
     *
     * @return list<RouteDefinition>
     */
    private function routesAdmin(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $controleur = $route->getAction('controller');
            $controleur = is_string($controleur) ? explode('@', $controleur)[0] : '';

            $estAdmin = str_starts_with($route->uri(), 'api/admin/')
                || str_contains($controleur, '\\Api\\Admin\\');

            if ($estAdmin && ! in_array($controleur, self::HORS_PERIMETRE, true)) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /** @var array<string, string>|null identifiants REELS, un par nom de parametre */
    private ?array $identifiants = null;

    /**
     * Sans identifiants reels, ce test ne prouve rien : le route-model-binding s'execute
     * AVANT le middleware admin, donc un identifiant absent rend 404 — et un 404 ne dit pas
     * si la garde aurait refuse. Mesure faite : avec un identifiant existant, une cliente
     * recoit bien 403 sur les memes routes.
     *
     * @return array<string, string>
     */
    private function identifiants(): array
    {
        if ($this->identifiants !== null) {
            return $this->identifiants;
        }

        $session = SchoolSession::factory()->create();
        $prestation = Prestation::create(['title' => 'Seance temoin', 'price' => 100]);

        return $this->identifiants = [
            'category' => EventCategory::create(['name' => 'Temoin', 'slug' => 'temoin'])->id,
            'client' => Client::factory()->create()->id,
            'export' => SchoolSessionExport::create(['school_session_id' => $session->id])->id,
            'gallery' => Gallery::factory()->create()->id,
            'giftCard' => GiftCard::factory()->create()->id,
            'giftCode' => GiftCode::factory()->create()->id,
            'gift_code' => GiftCode::factory()->create()->id,
            'order' => Order::factory()->create()->id,
            'photo' => Photo::factory()->create()->id,
            'privacyExport' => PrivacyExport::create([])->id,
            'prestation' => $prestation->id,
            'reservation' => Reservation::create(['prestation_id' => $prestation->id])->id,
            'schoolSession' => $session->id,
        ];
    }

    /**
     * Le chemin concret d'une route. Tout parametre non resolu reste marque : le test le
     * signale plutot que de le laisser passer en 404 silencieux.
     *
     * ⚠️ `{export}` designe DEUX modeles selon la route — `SchoolSessionExport` sous
     * `school-session-exports`, `PrivacyExport` sous `privacy/exports`. Meme nom, deux
     * tables : c'est le genre de collision que la separation par audience de D2 doit lever.
     *
     * @param  list<string>  $nonResolus
     */
    private function chemin(RouteDefinition $route, array &$nonResolus = []): string
    {
        $identifiants = $this->identifiants();
        $uriBrute = $route->uri();

        $uri = preg_replace_callback('/\{(\w+)\??\}/', function (array $trouve) use ($identifiants, $uriBrute, &$nonResolus) {
            $nom = $trouve[1];

            if ($nom === 'export' && str_contains($uriBrute, 'privacy/exports')) {
                $nom = 'privacyExport';
            }

            if (isset($identifiants[$nom])) {
                return $identifiants[$nom];
            }

            $nonResolus[] = $nom;

            return self::ID_INEXISTANT;
        }, $uriBrute);

        return '/'.ltrim($uri, '/');
    }

    private function verbe(RouteDefinition $route): string
    {
        foreach ($route->methods() as $methode) {
            if ($methode !== 'HEAD') {
                return $methode;
            }
        }

        return 'GET';
    }

    /**
     * Les deux acteurs refuses sont eprouves sur la surface ENTIERE : le middleware les
     * arrete avant le controleur, donc aucune ecriture n'est possible.
     *
     * Un echec liste les routes fautives plutot que de s'arreter a la premiere : quand une
     * refonte de routes en decouvre plusieurs, on veut les voir toutes d'un coup.
     */
    public function test_aucune_route_admin_n_est_ouverte_a_un_visiteur_anonyme(): void
    {
        $ouvertes = [];
        $nonResolus = [];

        foreach ($this->routesAdmin() as $route) {
            $statut = $this->json($this->verbe($route), $this->chemin($route, $nonResolus))->getStatusCode();

            if ($statut !== 401) {
                $ouvertes[] = $this->verbe($route).' '.$route->uri()." → {$statut}";
            }
        }

        $this->assertSame([], $ouvertes, "Routes admin atteignables sans authentification :\n  ".implode("\n  ", $ouvertes));
        $this->assertSame([], array_unique($nonResolus), 'Parametres sans identifiant reel : le test ne prouverait rien sur ces routes.');
    }

    public function test_aucune_route_admin_n_est_ouverte_a_une_cliente(): void
    {
        $cliente = User::factory()->create(['role' => 'client']);
        $ouvertes = [];
        $nonResolus = [];

        foreach ($this->routesAdmin() as $route) {
            $statut = $this->actingAs($cliente)->json($this->verbe($route), $this->chemin($route, $nonResolus))->getStatusCode();

            if ($statut !== 403) {
                $ouvertes[] = $this->verbe($route).' '.$route->uri()." → {$statut}";
            }
        }

        $this->assertSame([], $ouvertes, "Routes admin atteignables par une cliente :\n  ".implode("\n  ", $ouvertes));
        $this->assertSame([], array_unique($nonResolus), 'Parametres sans identifiant reel : le test ne prouverait rien sur ces routes.');
    }

    /**
     * Le pendant : la garde ne doit pas refuser celle pour qui elle est faite. Lectures
     * seulement — voir l'en-tete de classe.
     */
    public function test_l_administratrice_n_est_jamais_refusee_en_lecture(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $refusees = [];

        foreach ($this->routesAdmin() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $statut = $this->actingAs($admin)->getJson($this->chemin($route))->getStatusCode();

            if (in_array($statut, [401, 403], true)) {
                $refusees[] = $route->uri()." → {$statut}";
            }
        }

        $this->assertSame([], $refusees, "L'administratrice est refusée sur :\n  ".implode("\n  ", $refusees));
    }

    /** La surface ne doit pas retrecir en silence : un garde vide passerait tous les tests. */
    public function test_la_surface_admin_est_bien_enumeree(): void
    {
        $this->assertGreaterThanOrEqual(90, count($this->routesAdmin()));
    }

    /**
     * Un role inattendu n'est meme pas stockable : la contrainte `users_role_check` limite
     * la colonne a `admin` et `client`. C'est une protection plus forte qu'un test d'acces,
     * et elle merite d'etre verrouillee — la retirer rouvrirait la question.
     */
    public function test_la_base_refuse_tout_role_autre_qu_admin_ou_client(): void
    {
        $utilisateur = User::factory()->create(['role' => 'client']);

        $this->expectException(QueryException::class);

        $utilisateur->forceFill(['role' => 'Admin'])->save();
    }

    /** Un identifiant absent doit rendre 404, jamais 500 : la garde ne masque pas une erreur. */
    public function test_un_identifiant_absent_rend_404_et_non_500(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = self::ID_INEXISTANT;

        foreach (["/api/admin/clients/{$id}", "/api/admin/orders/{$id}", "/api/admin/galleries/{$id}"] as $chemin) {
            $this->actingAs($admin)->getJson($chemin)->assertNotFound();
        }
    }

    public function test_l_identifiant_temoin_est_bien_absent_de_la_base(): void
    {
        $this->assertTrue(Str::isUuid(self::ID_INEXISTANT));
        $this->assertDatabaseMissing('users', ['id' => self::ID_INEXISTANT]);
        $this->assertDatabaseMissing('orders', ['id' => self::ID_INEXISTANT]);
    }
}
