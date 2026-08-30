<?php

namespace Tests\Unit;

use App\Support\NumeroDeTelephone;
use PHPUnit\Framework\TestCase;

/**
 * Le tunnel demande `autocomplete="tel-national"` puis refusait ce que le navigateur rendait :
 * la validation exigeait dix chiffres colles, des deux cotes, sans normalisation.
 * L'autoremplissage menait donc a un formulaire refuse.
 *
 * Le pendant front est `web/src/utils/phone.ts` : les deux doivent accepter la meme chose,
 * sinon le front laisse passer ce que l'API refuse.
 */
class NumeroDeTelephoneTest extends TestCase
{
    /** La forme que le placeholder du site affiche lui-meme. */
    public function test_les_espaces_sont_acceptes(): void
    {
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('06 12 34 56 78'));
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser("06\u{00A0}12\u{00A0}34\u{00A0}56\u{00A0}78"));
    }

    public function test_les_separateurs_usuels_sont_acceptes(): void
    {
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('06.12.34.56.78'));
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('06-12-34-56-78'));
    }

    public function test_la_forme_internationale_devient_nationale(): void
    {
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('+33612345678'));
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('+33 6 12 34 56 78'));
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('0033612345678'));
    }

    public function test_un_numero_canonique_reste_intact(): void
    {
        $this->assertSame('0612345678', NumeroDeTelephone::normaliser('0612345678'));
    }

    public function test_l_absence_de_valeur_est_preservee(): void
    {
        $this->assertNull(NumeroDeTelephone::normaliser(null));
        $this->assertSame('', NumeroDeTelephone::normaliser('   '));
    }

    /** La normalisation ne doit pas fabriquer un numero valide a partir de n'importe quoi. */
    public function test_elle_ne_rend_pas_valide_ce_qui_ne_l_est_pas(): void
    {
        $this->assertSame('06123456', NumeroDeTelephone::normaliser('06 12 34 56'));
        $this->assertSame('0612345A78', NumeroDeTelephone::normaliser('06 12 34 5A 78'));
    }
}
