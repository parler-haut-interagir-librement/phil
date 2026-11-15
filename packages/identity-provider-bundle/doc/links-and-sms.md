# Sign-in links and phone numbers

## Sign-in links

The module `login_link` offers `/login/link`: the address typed, a link from the `login_link` authenticator of Symfony
Security sent to it through the Mailer component, and the same answer whether the address has an account or not. The
firewall configures the authenticator, its check route `phil_identity_provider_login_link_check`, its lifetime, its
uses and the properties it signs over — `userIdentifier` and `passwordChangedAt` keep a link from outliving a
password change:

```yaml
login_link:
    check_route: phil_identity_provider_login_link_check
    signature_properties: [userIdentifier, passwordChangedAt]
    lifetime: 600
    max_uses: 1
```

A sign-in link states no method: it counts for `IS_AUTHENTICATED_RECENTLY`, not for `PHIL_STEP_UP` of an account
that has a stronger method, and an account with an authenticator application is asked its code next.

## Phone numbers

The module `sms` verifies a phone number: `/account/phone` takes a number in E.164 — spaces, dashes, dots and
parentheses are dropped —, sends six digits through the port `SmsSender`, and verifies the number when the code comes
back. The code is kept hashed in the session with the number, an expiry of `sms.lifetime` seconds and `sms.attempts`
tries. The default `SmsSender` is the texter of the Notifier component, to whichever SMS transport the application
configured; `sms.sender` names a service of its own instead. The rate of sending belongs to the application's rate
limiter in front of the route.

A verified number lets the application reach the person, and appears in the claims of the scope `phone`. Text messages
as a second factor are planned, not implemented: SMS can be intercepted, and the authenticator application or a
passkey is the second factor the bundle offers.
