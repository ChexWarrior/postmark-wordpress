<a href="https://postmarkapp.com">
    <img src="assets/images/postmark.png" alt="Postmark Logo" title="Postmark" width="120" height="120" align="right">
</a>

# Postmark for WordPress

If you’re still sending email with default SMTP, you’re blind to delivery problems! Postmark for WordPress enables sites of any size to deliver and track WordPress notification emails reliably, with minimal setup time and zero maintenance.

If you don’t already have a Postmark account, you can get one in minutes, sign up at https://postmarkapp.com.

## Installation

1. Upload postmark directory to your /wp-content/plugins directory or install from plugin marketplace
2. Activate plugin in WordPress admin
3. In WordPress admin, go to **Settings** then **Postmark**. You will then want to insert your Postmark details. If you don’t already have a Postmark account, get one at http://postmarkapp.com.
4. Verify sending by entering a recipient email address you have access to and pressing the “Send Test Email” button. Enable logging for troubleshooting and to check the send result.
5. Once everything is verified as working, check **Send emails using Postmark** and Save, to override `wp_mail` to send using Postmark instead.

## FAQ

### What is Postmark?
Postmark is a hosted service that expertly handles all delivery of transactional webapp and web site email. This includes welcome emails, password resets, comment notifications, and more. If you've ever installed WordPress and had issues with PHP's mail() function not working right, or your WordPress install sends comment notifications or password resets to spam, Postmark makes all of these problems vanish in seconds. Without Postmark, you may not even know you're having delivery problems. Find out in seconds by installing and configuring this plugin.

### Will this plugin work with my WordPress site?

The Postmark for WordPress plugin overrides any usage of the `wp_mail()` function. Because of this, if any 3rd party code or plugins send mail directly using the PHP mail function, or any other method, we cannot override it. Please contact the makers of any offending plugins and let them know that they should use `wp_mail()` instead of unsupported mailing functions.

### Does this cost me money?

The Postmark service (and this plugin) are free to get started, for up to 100 emails a month. You can sign up at https://postmarkapp.com/. When you need to process more than 100 emails a month, Postmark offers monthly plans to fit your needs.

### My emails are still not sending, or they are going to spam! HELP!?

First, enable logging in **Settings** and check your send attempts for any errors returned by the Postmark API. These errors can let you know why the send attempts are failing. If you aren't seeing log entries for your send attempts, then the plugin or contact form generating the emails is likely not using `wp_mail()` and not compatible with this plugin.

If you are still unsure how to proceed, just send an email to support@postmarkapp.com or tweet @postmarkapp for help. Be sure to include as much detail as possible.

### Why should I trust you with my email sending?

Because we've been in this business for many years. We’ve been running an email marketing service, Newsberry, for five years. Through trial and error we already know what it takes to manage a large volume of email. We handle things like whitelisting, ISP throttling, reverse DNS, feedback loops, content scanning, and delivery monitoring to ensure your emails get to the inbox.

Most importantly, a great product requires great support and even better education. Our team is spread out across six time zones to offer fast support on everything from using Postmark to best practices on content and user engagement. A solid infrastructure only goes so far, that’s why improving our customer’s sending practices helps achieve incredible results.

### Why aren't my HTML emails being sent?

This plugin detects HTML by checking the headers sent by other WordPress plugins. If a "text/html" content type isn't set then this plugin won't send the HTML to Postmark to be sent out only the plain text version of the email.

## Additional Resources

[Postmark for WordPress FAQ](https://postmarkapp.com/support/article/1138-postmark-for-wordpress-faq)

[Can I use the Postmark for WordPress plugin with Gravity Forms?](https://postmarkapp.com/support/article/1129-can-i-use-the-postmark-for-wordpress-plugin-with-gravity-forms)

[How do I send with Ninja Forms and Postmark for WordPress?](https://postmarkapp.com/support/article/1047-how-do-i-send-with-ninja-forms-and-postmark-for-wordpress)

[How do I send with Contact Form 7 and Postmark for WordPress?](https://postmarkapp.com/support/article/1072-how-do-i-send-with-contact-form-7-and-postmark-for-wordpress)

[Can I use the Postmark for WordPress plugin with Divi contact forms?](https://postmarkapp.com/support/article/1128-can-i-use-the-postmark-for-wordpress-plugin-with-divi-contact-forms)

## Changelog

### v1.11.2
* Fixes no index error with track links check in wp_mail.

### v1.11.1
* Fixes call of non-global load_settings function during upgrade.

### v1.11.0
* Adds link tracking support.
* Fixes send test with HTML/open tracking option not being honored in sent test email.

### v1.10.6
* Fixes undefined index error.
* Adds Upgrade Notice

### v1.10.5
* Corrects logs deletion cron job unscheduling issue.

### v1.10.4
* Removes index on logs table.

### v1.10.3
* Corrects version mismatch in constructor.

### v1.10.1
* Adds a new logging feature that can be enabled to store logs for send attempts. Logs include Date, From address, To address, Subject, and Postmark API response. Logs are displayed in a Logs tab in the plugin setting once enabled.
* Switch loading of JS/CSS to use enqueue()

### v1.9.6
* Resolves issue when saving settings in UI.
* Falls attachment Content-Type back to 'application/octet-stream' when other methods fail.

### v1.9.5
* Update javascript to fix settings update issue.

### v1.9.4
* Added `postmark_error` and `postmark_response` actions to the plugin, to intercept API results after calling wp_mail. You can register callbacks for these using `add_action` (more info here: https://developer.wordpress.org/reference/functions/add_action/)

### v1.9.3
* Interface cleanup
* Minor code restructuring

### v1.9.2
* Make the errors available in the PHP variable `Postmark_Mail::$LAST_ERROR` if `wp_mail()` returns false, examine this variable to find out what happened.
* When doing a test send, if an error occurs, it will be printed in the settings page header.

### v1.9.1
* Fix case where 'From' header is specified as a literal string, instead of in an associative array.

### v1.9
* Allow the 'From' header to override the default sender.
* Don't send TextBody when the user has specified the 'Content-Type' header of 'text/html'
* Allow individual messages to opt-in to Track-Opens by including a header of 'X-PM-Track-Opens' and a value of `true`

### v1.8
* Modernization of codebase.

### v1.7
* Support headers for cc, bcc, and reply-to

### v1.6
* Added open tracking support.

### v1.5
* Fix issue with new WordPress HTTP API Integration.

### v1.4
* New option to force emails to be sent as HTML. Previously just detected Content-Type.
* Now uses the WordPress HTTP API.

### v1.3
* Resolved error with handing arrays of recipients

### v1.2
* Arrays of recipients are now properly handled
* HTML emails and Text Emails are now handled by checking the headers of the emails sent, and sends via Postmark appropriately.
* Optional "Powered by Postmark" footer of sent emails. "Postmark solves your WordPress email problems. Send transactional email confidently using http://postmarkapp.com"
* Add license to README and PHP file

### v1.0.0
* First Public release.
