<?php

namespace Tests\Feature;

use App\Events\ContactMessageSent;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactFormMail;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * POST /contact — formulaire public (ContactController@send).
 *
 * Régression : les listeners étaient enregistrés deux fois (Event::listen
 * explicite dans AppServiceProvider + découverte automatique de app/Listeners),
 * ce qui envoyait chaque mail en double.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'phone' => '0601020304',
            'subject' => 'Demande de devis',
            'message' => 'Bonjour, je souhaite un shooting.',
            'gdpr_consent' => true,
        ], $overrides);
    }

    public function test_contact_message_sent_has_exactly_one_listener(): void
    {
        $this->assertCount(1, Event::getListeners(ContactMessageSent::class));
    }

    public function test_each_email_is_sent_exactly_once(): void
    {
        $this->postJson('/api/contact', $this->payload())->assertOk();

        Mail::assertSent(ContactFormMail::class, 1);
        Mail::assertSent(ContactConfirmationMail::class, 1);
    }

    public function test_emails_reach_the_expected_recipients(): void
    {
        $this->postJson('/api/contact', $this->payload())->assertOk();

        Mail::assertSent(
            ContactFormMail::class,
            fn (ContactFormMail $mail) => $mail->hasTo(config('mail.admin_email')),
        );
        Mail::assertSent(
            ContactConfirmationMail::class,
            fn (ContactConfirmationMail $mail) => $mail->hasTo('jean@example.com'),
        );
    }

    public function test_message_is_stored_with_consent_and_send_flags(): void
    {
        $this->postJson('/api/contact', $this->payload())->assertOk();

        $message = ContactMessage::sole();

        $this->assertTrue($message->gdpr_consent);
        $this->assertNotNull($message->gdpr_consent_at);
        $this->assertTrue($message->email_sent_to_admin);
        $this->assertTrue($message->email_sent_to_user);
    }

    public function test_replaying_the_listener_does_not_resend_emails(): void
    {
        $this->postJson('/api/contact', $this->payload())->assertOk();

        $message = ContactMessage::sole();
        ContactMessageSent::dispatch($message, $this->payload());

        Mail::assertSent(ContactFormMail::class, 1);
        Mail::assertSent(ContactConfirmationMail::class, 1);
    }

    public function test_gdpr_consent_is_required(): void
    {
        $this->postJson('/api/contact', $this->payload(['gdpr_consent' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('gdpr_consent');

        Mail::assertNothingSent();
    }
}
