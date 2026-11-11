<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The sign-in and sign-up blocks: plain form elements, every word from a prop.
 */
#[CoversNothing]
final class BlockRecipesTest extends KitTestCase
{
    public function testASignInFormWithWhatItNeedsAlone(): void
    {
        self::assertSame(
            '<section data-slot="login-form" aria-labelledby="login-title" class="isCard isLoginForm"><header class="isCardHeader"><h2 id="login-title" class="isCardTitle">Sign in</h2></header><form method="post" action="/login" class="isCardBody isStack"><label for="login-username">Name</label><input id="login-username" type="text" name="_username" value="" autocomplete="username" required autofocus><label for="login-password">Password</label><input id="login-password" type="password" name="_password" autocomplete="current-password" required><button type="submit" class="isPrimary">Go</button></form></section>',
            $this->render('<twig:LoginForm action="/login" title="Sign in" usernameLabel="Name" passwordLabel="Password" submitLabel="Go" error="" />'),
        );
    }

    public function testASignInFormAfterAFailedAttempt(): void
    {
        self::assertSame(
            '<section data-slot="login-form" aria-labelledby="l-title" class="isCard isLoginForm"><header class="isCardHeader"><h2 id="l-title" class="isCardTitle">Sign in</h2><p class="isCardDescription">Welcome</p></header><form method="post" action="/login" class="isCardBody isStack"><p id="l-error" role="alert" class="isAlert isDanger">Invalid &lt;credentials&gt;.</p><label for="l-username">Email</label><input id="l-username" type="email" name="email" value="a@b.c" autocomplete="email" required aria-invalid="true" aria-describedby="l-error"><label for="l-password">Password</label><input id="l-password" type="password" name="pass" autocomplete="current-password" required autofocus aria-invalid="true" aria-describedby="l-error"><a class="isLoginFormForgot" href="/forgot">Forgot?</a><label><input type="checkbox" name="keep"> Remember me</label><input type="hidden" name="_csrf_token" value="tok"><input type="hidden" name="_target_path" value="/x"><button type="submit" class="isPrimary">Go</button></form><footer class="isCardFooter"><a href="/signup">Sign up</a></footer></section>',
            $this->render('<twig:LoginForm id="l" action="/login" title="Sign in" description="Welcome" usernameLabel="Email" usernameType="email" usernameField="email" passwordField="pass" passwordLabel="Password" submitLabel="Go" lastUsername="a@b.c" error="Invalid <credentials>." csrfToken="tok" rememberLabel="Remember me" rememberField="keep" forgotHref="/forgot" forgotLabel="Forgot?" signupHref="/signup" signupLabel="Sign up"><input type="hidden" name="_target_path" value="/x"></twig:LoginForm>'),
        );
    }

    public function testALinkNeedsItsAddressAndItsText(): void
    {
        $html = $this->render('<twig:LoginForm action="/login" title="T" usernameLabel="U" passwordLabel="P" submitLabel="S" forgotHref="/f" signupLabel="Join" csrfField="_t" />');

        self::assertStringNotContainsString('isLoginFormForgot', $html);
        self::assertStringNotContainsString('<footer', $html);
        self::assertStringNotContainsString('type="hidden"', $html);
        self::assertStringNotContainsString('<footer', $this->render('<twig:SignupForm action="/s" title="T" emailLabel="E" passwordLabel="P" submitLabel="S" loginHref="/l" />'));
    }

    public function testASignUpFormWithWhatItNeedsAlone(): void
    {
        self::assertSame(
            '<section data-slot="signup-form" aria-labelledby="signup-title" class="isCard isSignupForm"><header class="isCardHeader"><h2 id="signup-title" class="isCardTitle">Join</h2></header><form method="post" action="/signup" class="isCardBody isStack"><div class="isField"><label for="signup-email">Email</label><input id="signup-email" type="email" name="email" autocomplete="email" required value=""></div><div class="isField"><label for="signup-password">Password</label><input id="signup-password" type="password" name="password" autocomplete="new-password" required minlength="12"></div><button type="submit" class="isPrimary">Join</button></form></section>',
            $this->render('<twig:SignupForm action="/signup" title="Join" emailLabel="Email" passwordLabel="Password" submitLabel="Join" />'),
        );
    }

    public function testASignUpFormRefusedNeverWritesBackAPassword(): void
    {
        self::assertSame(
            '<section data-slot="signup-form" aria-labelledby="s-title" class="isCard isSignupForm"><header class="isCardHeader"><h2 id="s-title" class="isCardTitle">Join</h2><p class="isCardDescription">Free</p></header><form method="post" action="/signup" class="isCardBody isStack"><div class="isField"><label for="s-name">Name</label><input id="s-name" type="text" name="full" autocomplete="name" required value="Ann"></div><div class="isField isDanger"><label for="s-email">Email</label><input id="s-email" type="email" name="mail" autocomplete="email" required value="a@b.c" aria-describedby="s-email-error" aria-invalid="true"><ul id="s-email-error" class="isFieldError"><li>Taken</li></ul></div><div class="isField isDanger"><label for="s-password">Password</label><input id="s-password" type="password" name="pw" autocomplete="new-password" required minlength="14" aria-describedby="s-password-hint s-password-error" aria-invalid="true"><p id="s-password-hint" class="isFieldDescription">14 characters</p><ul id="s-password-error" class="isFieldError"><li>Short</li><li>Weak</li></ul></div><div class="isField"><label for="s-confirm">Again</label><input id="s-confirm" type="password" name="pw2" autocomplete="new-password" required minlength="14"></div><input type="hidden" name="tk" value="t"><button type="submit" class="isPrimary">Join</button></form><footer class="isCardFooter"><a href="/login">Sign in</a></footer></section>',
            $this->render('<twig:SignupForm id="s" action="/signup" title="Join" description="Free" nameLabel="Name" nameField="full" emailLabel="Email" emailField="mail" passwordLabel="Password" passwordField="pw" confirmLabel="Again" confirmField="pw2" passwordHint="14 characters" :passwordMinLength="14" submitLabel="Join" :values="{full: \'Ann\', mail: \'a@b.c\', pw: \'leak\', pw2: \'leak\'}" :errors="{mail: [\'Taken\'], pw: [\'Short\', \'Weak\']}" csrfToken="t" csrfField="tk" loginHref="/login" loginLabel="Sign in" />'),
        );
    }

    public function testTheHintOfThePasswordAloneDescribesIt(): void
    {
        self::assertStringContainsString(
            'minlength="12" aria-describedby="signup-password-hint"><p id="signup-password-hint" class="isFieldDescription">Long</p></div>',
            $this->render('<twig:SignupForm action="/s" title="T" emailLabel="E" passwordLabel="P" passwordHint="Long" submitLabel="S"><label><input type="checkbox" name="terms" required> Terms</label></twig:SignupForm>'),
        );
    }
}
