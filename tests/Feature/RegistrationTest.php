<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use App\User;
use App\Mail\PleaseConfirmYourEmail;

class RegistrationTest extends TestCase
{
   /** @test */
   public function a_confirmation_email_is_sent_upon_registration()
   {
       Mail::fake();

       // 用路由命名代替 url
       $this->post(route('register'),[
           'name' => 'NoNo1',
           'email' => 'NoNo1@example.com',
           'password' => '123456',
           'password_confirmation' => '123456'
       ]);

       Mail::assertSent(PleaseConfirmYourEmail::class);
   }

    /** @test */
    public function user_can_fully_confirm_their_email_addresses()
    {
        $this->post('/register',[
            'name' => 'NoNo1',
            'email' => 'NoNo1@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);

        $user = User::whereName('NoNo1')->first();

        // 新注册用户未认证，且拥有 confirmation_token
        $this->assertFalse($user->confirmed);
        $this->assertNotNull($user->confirmation_token);

        // 用路由命名代替 url
        $this->get(route('register.confirm',['token' => $user->confirmation_token]))
            ->assertRedirect(route('threads'));

        $this->assertTrue($user->fresh()->confirmed);
    }

    /** @test */
    public function confirming_an_invalid_token()
    {
        // 测试无效 Token
        $this->get(route('register.confirm',['token' => 'invalid']))
            ->assertRedirect(route('threads'))
            ->assertSessionHas('flash','Unknown token.');
    }
}
