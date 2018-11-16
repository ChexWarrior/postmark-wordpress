<?php
if (!defined('ABSPATH')) {
    die();
}

require_once(dirname( __FILE__ ).'/postmark.php');

// Exit if WP-CLI is not present.
if ( !defined( 'WP_CLI' ) ) return;

# Registers a custom WP-CLI command for sending a test email.
#
# Example usage:
#
# $ wp postmarksendtestemail recipient@domain.com --from="senderoverride@domain.com" --subject="my custom subject" --body="<b>this is some test html</b>" --opentracking="true"
#
# Success: Successfully sent a test email to recipient@domain.com.

/**
 * Send a test email
 * <recipientemail>
 * : Address to send test to.
 *
 * [--from=<fromemailaddress>]
 * : Test email address to send from
 *
 * [--subject=<subject>]
 * : Test email subject
 *
 * [--body=<body>]
 * : Test email body content
 *
 * [--opentracking=<true>]
 * : Sets the open tracking flag, true or false.
 *
 * @when before_wp_load
 */
$sendtestemail = function ($args, $assoc_args) {
  $headers = array();

  // Make sure To email address is present and valid.
  if ( isset($args[0]) && is_email($args[0])) {
    $to = sanitize_email($args[0]);
  } else {
    WP_CLI::error('You need to specify a valid recipient email address.');
  }

  // Checks for a From address.
  if ( isset($assoc_args['from']) && is_email($assoc_args['from'])) {
    $from = sanitize_email($assoc_args['from']);
    array_push( $headers, 'From:' . $from );
    // Sets the From address override.
  }

  // Checks if a subject was specified and use it.
  if ( isset($assoc_args['subject'])) {
    $subject = $assoc_args['subject'];

  // Uses a default subject if not specified.
  } else {
    $subject = 'Postmark Plugin WP-CLI Test: ' . get_bloginfo( 'name' );
  }

  // Checks if a body was specified and use it.
  if ( isset($assoc_args['body']) ) {
      $message = $assoc_args['body'];

  // Uses a default body if not specified.
  } else {
      $message = 'This is a test email generated from the Postmark for WordPress plugin using the WP-CLI.';
  }

  // Sets open tracking flag.
  if (isset($assoc_args['opentracking']) && $assoc_args['opentracking'] == 'true') {
    $headers['X-PM-Track-Opens'] = true;
  } else {
    $headers['X-PM-Track-Opens'] = false;
  }

  // Sends the test email.
  $response = wp_mail( $to, $subject, $message, $headers );

  // If all goes well, display a success message using the CLI.
  if ( false !== $response ) {
      WP_CLI::success('Successfully sent a test email to ' . $to . '.'
    );
  }
  else {
      $dump = print_r( Postmark_Mail::$LAST_ERROR, true );
      WP_CLI::warning('Test send failed.');
      WP_CLI::warning('Response: ' . $dump);
  }
};

// Make sure Postmark exists before adding wp cli command to
// send a test email.
if( class_exists( 'Postmark_Mail' ) ) {
    WP_CLI::add_command( 'postmarksendtestemail', $sendtestemail);
} else {
  WP_CLI::error('Postmark_Mail class not found');
}