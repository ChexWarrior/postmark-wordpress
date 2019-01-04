<?php
if (!defined('ABSPATH')) {
    die();
}

require_once(dirname( __FILE__ ).'/postmark.php');

$postmark_settings = json_decode(get_option( 'postmark_settings' ), true);

// Exit if WP-CLI is not present.
if ( !defined( 'WP_CLI' ) ) return;

/**********************************************
************** Send a Test Email **************
**********************************************/

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

/**********************************************
***************** Bounces API *****************
**********************************************/

# Registers a custom WP-CLI command for retrieving
# delivery stats.
#
# Example usage:
#
# $ wp postmarkgetdeliverystats
#
# Success: {
#  {
#  "InactiveMails": 192,
#  "Bounces": [
#    {
#      "Name": "All",
#      "Count": 253
#    },
#    {
#      "Type": "HardBounce",
#      "Name": "Hard bounce",
#      "Count": 195
#    },
#    {
#      "Type": "Transient",
#      "Name": "Message delayed",
#      "Count": 10
#    },
#    {
#      "Type": "AutoResponder",
#      "Name": "Auto responder",
#      "Count": 14
#    },
#    {
#      "Type": "SpamNotification",
#      "Name": "Spam notification",
#      "Count": 3
#    },
#    {
#      "Type": "SoftBounce",
#      "Name": "Soft bounce",
#      "Count": 30
#    },
#    {
#      "Type": "SpamComplaint",
#      "Name": "Spam complaint",
#      "Count": 1
#    }
#  ]
# }

/**
 * Retrieves delivery stats
 *
 * @when after_wp_load
 */
$getdeliverystats = function() use ($postmark_settings) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting delivery stats', $count );

  $progress->tick();

  if(isset($postmark_settings["api_key"])) {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }

  $progress->tick();

  $url = "https://api.postmarkapp.com/deliverystats";

  $progress->tick();

  // Retrieves delivery stats
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved delivery stats using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning('Delivery stats retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));
  } else {
    $progress->finish();
    WP_CLI::warning('Delivery stats retrieval failed.');
  }
};

# Registers a custom WP-CLI command for retrieving bounces.
#
# Example usage:
#
# $ wp postmarkgetbounces
#
# Success: {
#  "TotalCount": 253,
#  "Bounces": [
#    {
#      "ID": 692560173,
#      "Type": "HardBounce",
#      "TypeCode": 1,
#      "Name": "Hard bounce",
#      "Tag": "Invitation",
#      "MessageID": "2c1b63fe-43f2-4db5-91b0-8bdfa44a9316",
#      "ServerID": 23,
#      "Description": "The server was unable to deliver your message (ex: unknown user, mailbox not found).",
#      "Details": "action: failed\r\n",
#      "Email": "anything@blackhole.postmarkapp.com",
#      "From": "sender@postmarkapp.com",
#      "BouncedAt": "2014-01-15T16:09:19.6421112-05:00",
#      "DumpAvailable": false,
#      "Inactive": false,
#      "CanActivate": true,
#      "Subject": "SC API5 Test"
#    },
#    {
#      "ID": 676862817,
#      "Type": "HardBounce",
#      "TypeCode": 1,
#      "Name": "Hard bounce",
#      "Tag": "Invitation",
#      "MessageID": "623b2e90-82d0-4050-ae9e-2c3a734ba091",
#      "ServerID": 23,
#      "Description": "The server was unable to deliver your message (ex: unknown user, mailbox not found).",
#      "Details": "smtp;554 delivery error: dd This user doesn't have a yahoo.com account (vicelcown@yahoo.com) [0] - mta1543.mail.ne1.yahoo.com",
#      "Email": "vicelcown@yahoo.com",
#      "From": "sender@postmarkapp.com",
#      "BouncedAt": "2013-10-18T09:49:59.8253577-04:00",
#      "DumpAvailable": false,
#      "Inactive": true,
#      "CanActivate": true,
#      "Subject": "Production API Test"
#    }
#  ]
# }

/**
 * Retrieve bounces
 *
 * [--count=<count>]
 * : Number of bounces to return per request. Max 500.
 *
 * [--offset=<offset>]
 * : Number of bounces to skip.
 *
 * [--type=<type>]
 * : Filter by type of bounce.
 *
 * [--inactive=<inactive>]
 * : Filter by emails that were deactivated by Postmark due to the bounce.
 * Set to true or false. If this isn’t specified it will return both active and
 * inactive.
 *
 * [--emailFilter=<emailaddress>]
 * Filter by email address.
 *
 * [--tag=<tag>]
 * Filter by tag.
 *
 * [--messageID=<messageID>]
 * Filter by messageID.
 *
 * [--fromdate=<fromdate>]
 * Filter messages starting from the date specified (inclusive). e.g. 2014-02-01
 *
 * [--todate=<todate>]
 * Filter messages up to the date specified (inclusive). e.g. 2014-02-01.
 *
 * @when after_wp_load
 */
$getbounces = function($assoc_args) use ($postmark_settings) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting bounces', $count );

  $progress->tick();

  if(isset($postmark_settings["api_key"])) {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }

  // Checks for a count parameter and uses it if set.
  if ( isset($assoc_args['count']) && is_int($assoc_args['count']) && $assoc_args['count'] < 501 && $assoc_args['count'] > 0) {
    $count = $assoc_args['count'];

  // Uses 500 for default count if count not specified.
  } elseif (!isset($assoc_args['count'])) {
    $count = 500;
  }

  // Checks for an offset parameter and uses it if set.
  if ( isset($assoc_args['offset']) && is_int($assoc_args['offset'])) {
    $offset = $assoc_args['offset'];

  // Uses 0 for default offset if offset not specified.
  } elseif (!isset($assoc_args['offset'])) {
    $offset = 0;
  }

  // Checks if a type was specified and uses it.
  if ( isset($assoc_args['type'])) {
    $type = $assoc_args['type'];
  }

  // Checks if inactive was specified and uses it.
  if ( isset($assoc_args['inactive']) ) {
      $inactive = $assoc_args['inactive'];
  }

  // Checks if emailFilter was specified and uses it.
  if ( isset($assoc_args['emailFilter']) ) {
    if (is_email($assoc_args['emailFilter'])) {
      $emailFilter = sanitize_email($assoc_args['emailFilter']);
    } else {
      WP_CLI::error('emailFilter not a valid email address.');
    }
  }

  // Checks if tag was specified and uses it.
  if ( isset($assoc_args['tag']) ) {
    $tag = $assoc_args['tag'];
  }

  // Checks if messageID was specified and uses it.
  if ( isset($assoc_args['messageID']) ) {
    $messageID = $assoc_args['messageID'];
  }

  // Checks if fromdate was specified and uses it.
  if ( isset($assoc_args['fromdate']) ) {
    $fromdate = $assoc_args['fromdate'];
  }

  // Checks if todate was specified and uses it.
  if ( isset($assoc_args['todate']) ) {
    $todate = $assoc_args['todate'];
  }

  $progress->tick();

  $url = "https://api.postmarkapp.com/bounces";

  if(isset($count)) {
    $url .= "?count=" . $count;
  } else {
    $url .= "?count=" . 500;
  }

  if(isset($offset)) {
    $url .= "&offset=" . $offset;
  } else {
    $url .= "&offset=" . 0;
  }

  if (isset($type)) {
    $url .= "&type=" . $type;
  }

  if (isset($inactive)) {
    $url .= "&inactive=" . $inactive;
  }

  if (isset($emailFilter)) {
    $url .= "&emailFilter=" . $emailFilter;
  }

  if (isset($tag)) {
    $url .= "&tag=" . $tag;
  }

  if (isset($messageID)) {
    $url .= "&messageID=" . $messageID;
  }

  if (isset($fromdate)) {
    $url .= "&fromdate=" . $fromdate;
  }

  if (isset($todate)) {
    $url .= "&todate=" . $todate;
  }

  $progress->tick();

  // Retrieve bounces
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved bounces using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning('Bounce retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));
  } else {
    $progress->finish();
    WP_CLI::warning('Bounce retrieval failed.');
  }
};

/**
 * Retrieve a single bounce
 *
 * <bounceid>
 * : ID of bounce.
 *
 * @when after_wp_load
 */
$getbounce = function ($args) use ($postmark_settings) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting bounces', $count );

  $progress->tick();

  if(isset($postmark_settings["api_key"])) {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }

  // Makes sure bounce id provided in command.
  if ( isset($args['0'])) {
    $bounceid = $args['0'];
    $url = "https://api.postmarkapp.com/bounces/" . $args['0'];
  } else {
    WP_CLI::error('Specify a bounce id.');
  }

  $progress->tick();

  // Retrieve the bounce
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved bounce using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning('Bounce retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning('Bounce retrieval failed.');
  }
};

/**
 * Retrieves raw source of bounce. If no dump is available
 * this will return an empty string.
 *
 * <bounceid>
 * : ID of bounce.
 *
 * @when after_wp_load
 */
$getbouncedump = function ($args) use ($postmark_settings) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting bounces', $count );

  $progress->tick();

  if(isset($postmark_settings["api_key"])) {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }

  // Makes sure bounce id provided in command.
  if ( isset($args['0'])) {
    $bounceid = $args['0'];
    $url = "https://api.postmarkapp.com/bounces/" . $args['0'] . "/dump";
  } else {
    WP_CLI::error('Specify a bounce id.');
  }

  $progress->tick();

  // Retrieve the bounce dump
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved bounce dump using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning('Bounce dump retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning('Bounce dump retrieval failed.');
  }
};

/**
 * Reactivates a bounced address.
 *
 * <bounceid>
 * : ID of bounce.
 *
 * @when after_wp_load
 */
$activatebounce = function ($args) use ($postmark_settings) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting bounces', $count );

  $progress->tick();

  if(isset($postmark_settings["api_key"])) {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
    $headers['Accept'] = 'application/json';
    $headers['Content-Type'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }

  // Makes sure bounce id provided in command.
  if ( isset($args['0'])) {
    $bounceid = $args['0'];
    $url = "https://api.postmarkapp.com/bounces/" . $args['0'] . "/activate";
  } else {
    WP_CLI::error('Specify a bounce id to reactivate.');
  }

  $progress->tick();

  $options = array(
        'method'     => 'PUT',

        // Must include a body for this API call.
        'body'       => ' ',

        'headers'    => $headers
        );

  $response = wp_remote_request( $url, $options );

  $progress->tick();

  // If all goes well, display the retrieved bounce dump using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning('Bounce activation failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::error('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning('Bounce activation failed.');
  }
};

/**
 * Get an array of tags that have generated bounces for a given
 * server.
 *
 *
 * @when after_wp_load
 */
$getbouncedtags = function ($args) use ($postmark_settings) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting bounces', $count );

  $progress->tick();

  if(isset($postmark_settings["api_key"])) {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }


  $url = "https://api.postmarkapp.com/bounces/tags";

  $progress->tick();

  // Retrieve the bounce
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved bounce using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning('Bounced tags retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning('Bounced tags retrieval failed.');
  }
};

/**********************************************
***************** Servers API *****************
**********************************************/

# Registers a custom WP-CLI command for retrieving
# a single server.
#
# Example usage:
#
# $ wp postmarkgetserver <account api token> <serverid>
#
# Success: {
#  "ID": 1,
#  "Name": "Staging Testing",
#  "ApiTokens": [
#    "server token"
#  ],
#  "ServerLink": "https://postmarkapp.com/servers/1/overview",
#  "Color": "red",
#  "SmtpApiActivated": true,
#  "RawEmailEnabled": false,
#  "DeliveryHookUrl": "http://hooks.example.com/delivery",
#  "InboundAddress": "yourhash@inbound.postmarkapp.com",
#  "InboundHookUrl": "http://hooks.example.com/inbound",
#  "BounceHookUrl": "http://hooks.example.com/bounce",
#  "IncludeBounceContentInHook": true,
#  "OpenHookUrl": "http://hooks.example.com/open",
#  "PostFirstOpenOnly": false,
#  "TrackOpens": false,
#  "TrackLinks": "None",
#  "ClickHookUrl": "http://hooks.example.com/click",
#  "InboundDomain": "",
#  "InboundHash": "yourhash",
#  "InboundSpamThreshold": 0,
#  "EnableSmtpApiErrorHooks": false
# }

/**
 * Retrieves a single server's details.
 *
 * <accountkey>
 * : Account API token.
 *
 * <serverid>
 * : Server ID of the server to retrieve.
 *
 * @when after_wp_load
 */
$getserver = function($args, $assoc_args) {

  $url = "https://api.postmarkapp.com/servers/{$args[1]}";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Getting Server ID {$args[1]}", $count );

  $progress->tick();

  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';

  $progress->tick();

  // Retrieves server details.
  $response = wp_remote_get($url, array( 'headers' => $headers));

  $progress->tick();

  // If all goes well, display the retrieved server using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();

    $response['body'] = json_decode($response['body']);

    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  } elseif ( is_array( $response ) && false == (wp_remote_retrieve_response_code( $response ) == 200 )) {

    $progress->finish();

    WP_CLI::warning('Server retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  } else {
    $progress->finish();
    WP_CLI::warning('Server retrieval failed.');
  }
};

# Registers a custom WP-CLI command for creating
# a server.
#
# Example usage:
#
# $ wp postmarkcreateserver <account api token> <name>
#
# Success: {
#  "ID": 1,
#  "Name": "Staging Testing",
#  "ApiTokens": [
#    "server token"
#  ],
#  "ServerLink": "https://postmarkapp.com/servers/1/overview",
#  "Color": "red",
#  "SmtpApiActivated": true,
#  "RawEmailEnabled": false,
#  "DeliveryHookUrl": "http://hooks.example.com/delivery",
#  "InboundAddress": "yourhash@inbound.postmarkapp.com",
#  "InboundHookUrl": "http://hooks.example.com/inbound",
#  "BounceHookUrl": "http://hooks.example.com/bounce",
#  "IncludeBounceContentInHook": true,
#  "OpenHookUrl": "http://hooks.example.com/open",
#  "PostFirstOpenOnly": false,
#  "TrackOpens": false,
#  "TrackLinks": "None",
#  "ClickHookUrl": "http://hooks.example.com/click",
#  "InboundDomain": "",
#  "InboundHash": "yourhash",
#  "InboundSpamThreshold": 0,
#  "EnableSmtpApiErrorHooks": false
# }

/**
 * Creates a new server.
 *
 * <accountkey>
 * : Account API token.
 *
 * <name>
 * : Name of server.
 *
 * [--servercolor=<servercolor>]
 * : Color of the server in the rack screen. Purple Blue Turqoise Green Red
 * Yellow Grey Orange
 *
 * TODO: Check if you can just pass in as a flag without a value for true/false.
 * [--smtpapiactivated=<smtpapiactivated>]
 * : Specifies whether or not SMTP is enabled on this server.
 *
 * [--rawemailenabled]
 * : When enabled, the raw email content will be included with inbound webhook
 * payloads under the RawEmail key.
 *
 * [--deliveryhookurl=<deliveryhookurl>]
 * : URL to POST to every time email is delivered.
 *
 * [--inboundhookurl=<inboundhookurl>]
 * : URL to POST to every time an inbound event occurs.
 *
 * [--bouncehookurl=<bouncehookurl>]
 * : URL to POST to every time a bounce event occurs.
 *
 * [--includebouncecontentinhook]
 * : Include bounce content in webhook.
 *
 * [--openhookurl=<openhookurl>]
 * : URL to POST to every time an open event occurs.
 *
 * [--postfirstopenonly]
 * : If set to true, only the first open by a particular recipient will initiate
 * the open webhook. Any subsequent opens of the same email by the same
 * recipient will not initiate the webhook.
 *
 * [--trackopens]
 * : Indicates if all emails being sent through this server have open tracking
 * enabled.
 *
 * [--tracklinks=<tracklinks>]
 * : Indicates if all emails being sent through this server should have link
 * tracking enabled for links in their HTML or Text bodies. Possible options:
 * None HtmlAndText HtmlOnly TextOnly
 *
 * [--clickhookurl=<clickhookurl>]
 * : URL to POST to when a unique click event occurs.
 *
 * [--inbounddomain=<inbounddomain>]
 * : Inbound domain for MX setup.
 *
 * [--inboundspamthreshold=<inboundspamthreshold>]
 * : The maximum spam score for an inbound message before it's blocked.
 *
 * [--enablesmtpapierrorhooks]
 * : Specifies whether or not SMTP API Errors will be included with bounce
 * webhooks.
 *
 * @when after_wp_load
 */
$createserver = function( $args, $assoc_args) {

  $url = "https://api.postmarkapp.com/servers";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Creating new server -  {$args[1]}", $count );

  $progress->tick();

  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';
  $headers['Content-Type'] = 'application/json';

  $progress->tick();

  $new_server = array("Name" => "{$args[1]}");

  if( isset( $assoc_args['smtpapiactivated'] ) ) {
    $new_server['SmtpApiActivated'] = "true";
  }

  if( isset( $assoc_args['rawemailenabled'] ) ) {
    $new_server['RawEmailEnabled'] = "true";
  }

  if( isset( $assoc_args['deliveryhookurl'] ) ) {
    $new_server['DeliveryHookUrl'] = $assoc_args['deliveryhookurl'];
  }

  if( isset( $assoc_args['inboundhookurl'] ) ) {
    $new_server['InboundHookUrl'] = $assoc_args['inboundhookurl'];
  }

  if( isset( $assoc_args['bouncehookurl'] ) ) {
    $new_server['BounceHookUrl'] = $assoc_args['bouncehookurl'];
  }

  if( isset( $assoc_args['includebouncecontentinhook'] ) ) {
    $new_server['IncludeBounceContentInHook'] = "true";
  }

  if( isset( $assoc_args['openhookurl'] ) ) {
    $new_server['OpenHookUrl'] = $assoc_args['openhookurl'];
  }

  if( isset( $assoc_args['postfirstopenonly'] ) ) {
    $new_server['PostFirstOpenOnly'] = "true";
  }

  if( isset( $assoc_args['trackopens'] ) ) {
    $new_server['TrackOpens'] = "true";
  }

  if( isset( $assoc_args['clickhookurl'] ) ) {
    $new_server['ClickHookUrl'] = $assoc_args['clickhookurl'];
  }

  if( isset( $assoc_args['inbounddomain'] ) ) {
    $new_server['InboundDomain'] = $assoc_args['inbounddomain'];
  }

  if( isset( $assoc_args['inboundspamthreshold'] ) && is_int( $assoc_args['inboundspamthreshold'] ) ) {
    $new_server['InboundSpamThreshold'] = $assoc_args['inboundspamthreshold'];
  }

  if( isset( $assoc_args['enablesmtpapierrorhooks'] ) ) {
    $new_server['EnableSmtpApiErrorHooks'] = "true";
  }

  var_dump($assoc_args);

  // Uses server color if present.
  if ( isset( $assoc_args['servercolor'] ) ) {

    switch ($assoc_args['servercolor']) {
      case "purple":
        $new_server['Color'] = "purple";
        break;

      case "blue":
        $new_server['Color'] = "blue";
        break;

      case 'turqoise':
        $new_server['Color'] = "turqoise";
        break;

      case 'green':
        $new_server['Color'] = "green";
        break;

      case 'red':
        $new_server['Color'] = "red";
        break;

      case 'yellow':
        $new_server['Color'] = "yellow";
        break;

      case 'grey':
        $new_server['Color'] = "grey";
        break;

      case 'orange':
        $new_server['Color'] = "orange";
        break;

      default:
        WP_CLI::Warning("Possible values for colors are purple, blue, turquoise, green, red, yellow, grey, or orange.");
        break;
    }
  }

  // Uses track links value if present.
  if ( isset($assoc_args['tracklinks']) ) {
    switch ($assoc_args['tracklinks']) {
      case 'None':
        $new_server['TrackLinks'] = "None";
        break;
      case 'HtmlAndText':
        $new_server['TrackLinks'] = "HtmlAndText";
        break;
      case 'HtmlOnly':
        $new_server['TrackLinks'] = "HtmlOnly";
        break;
      case 'TextOnly':
        $new_server['TrackLinks'] = "TextOnly";
        break;
      default:
        WP_CLI::Warning("Possible values for track links option are None, HtmlAndText, HtmlOnly, or TextOnly. Setting to None.");
        break;
    }
  }

  $options = array(
    'method' => 'POST',
    'blocking' => true,
    'headers' => $headers,
    'body' => json_encode( $new_server )
  );

  var_dump($options);

  // Creates new server.
  $response = wp_remote_post($url, $options);

  $progress->tick();

  // If all goes well, display the new server's details using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();

    $response['body'] = json_decode($response['body']);

    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  } elseif ( is_array( $response ) && false == (wp_remote_retrieve_response_code( $response ) == 200 )) {

    $progress->finish();

    WP_CLI::warning('Server creation failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  } else {
    $progress->finish();
    WP_CLI::warning('Server creation failed.');
  }
};

# Registers a custom WP-CLI command for editing
# a server.
#
# Example usage:
#
# $ wp postmarkcreateserver <account api token> <serverid>
#
# Success: {
#  "ID": 1,
#  "Name": "Staging Testing",
#  "ApiTokens": [
#    "server token"
#  ],
#  "ServerLink": "https://postmarkapp.com/servers/1/overview",
#  "Color": "red",
#  "SmtpApiActivated": true,
#  "RawEmailEnabled": false,
#  "DeliveryHookUrl": "http://hooks.example.com/delivery",
#  "InboundAddress": "yourhash@inbound.postmarkapp.com",
#  "InboundHookUrl": "http://hooks.example.com/inbound",
#  "BounceHookUrl": "http://hooks.example.com/bounce",
#  "IncludeBounceContentInHook": true,
#  "OpenHookUrl": "http://hooks.example.com/open",
#  "PostFirstOpenOnly": false,
#  "TrackOpens": false,
#  "TrackLinks": "None",
#  "ClickHookUrl": "http://hooks.example.com/click",
#  "InboundDomain": "",
#  "InboundHash": "yourhash",
#  "InboundSpamThreshold": 0,
#  "EnableSmtpApiErrorHooks": false
# }

/**
 * Edits an existing server.
 *
 * <accountkey>
 * : Account API token.
 *
 * <serverid>
 * : ID of server to edit.
 *
 * * [--name=<name>]
 * : Name of the server.
 *
 * [--servercolor=<servercolor>]
 * : Color of the server in the rack screen. Purple Blue Turqoise Green Red
 * Yellow Grey Orange
 *
 * [--smtpapiactivated=<smtpapiactivated>]
 * : Specifies whether or not SMTP is enabled on this server.
 *
 * [--rawemailenabled]
 * : When enabled, the raw email content will be included with inbound webhook
 * payloads under the RawEmail key.
 *
 * [--deliveryhookurl=<deliveryhookurl>]
 * : URL to POST to every time email is delivered.
 *
 * [--inboundhookurl=<inboundhookurl>]
 * : URL to POST to every time an inbound event occurs.
 *
 * [--bouncehookurl=<bouncehookurl>]
 * : URL to POST to every time a bounce event occurs.
 *
 * [--includebouncecontentinhook]
 * : Include bounce content in webhook.
 *
 * [--openhookurl=<openhookurl>]
 * : URL to POST to every time an open event occurs.
 *
 * [--postfirstopenonly]
 * : If set to true, only the first open by a particular recipient will initiate
 * the open webhook. Any subsequent opens of the same email by the same
 * recipient will not initiate the webhook.
 *
 * [--trackopens]
 * : Indicates if all emails being sent through this server have open tracking
 * enabled.
 *
 * [--tracklinks=<tracklinks>]
 * : Indicates if all emails being sent through this server should have link
 * tracking enabled for links in their HTML or Text bodies. Possible options:
 * None HtmlAndText HtmlOnly TextOnly
 *
 * [--clickhookurl=<clickhookurl>]
 * : URL to POST to when a unique click event occurs.
 *
 * [--inbounddomain=<inbounddomain>]
 * : Inbound domain for MX setup.
 *
 * [--inboundspamthreshold=<inboundspamthreshold>]
 * : The maximum spam score for an inbound message before it's blocked.
 *
 * [--enablesmtpapierrorhooks]
 * : Specifies whether or not SMTP API Errors will be included with bounce
 * webhooks.
 *
 * @when after_wp_load
 */
$editserver = function( $args, $assoc_args) {

  $url = "https://api.postmarkapp.com/servers/{$args[1]}";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Editing server ID {$args[1]}", $count );

  $progress->tick();

  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';
  $headers['Content-Type'] = 'application/json';

  $progress->tick();

  $server_edits = array();

  if( isset( $assoc_args['name'] ) ) {
    $server_edits['Name'] = $assoc_args['name'];
  }

  if( isset( $assoc_args['smtpapiactivated'] ) ) {
    $server_edits['SmtpApiActivated'] = "true";
  }

  if( isset( $assoc_args['rawemailenabled'] ) ) {
    $server_edits['RawEmailEnabled'] = "true";
  }

  if( isset( $assoc_args['deliveryhookurl'] ) ) {
    $server_edits['DeliveryHookUrl'] = $assoc_args['deliveryhookurl'];
  }

  if( isset( $assoc_args['inboundhookurl'] ) ) {
    $server_edits['InboundHookUrl'] = $assoc_args['inboundhookurl'];
  }

  if( isset( $assoc_args['bouncehookurl'] ) ) {
    $server_edits['BounceHookUrl'] = $assoc_args['bouncehookurl'];
  }

  if( isset( $assoc_args['includebouncecontentinhook'] ) ) {
    $server_edits['IncludeBounceContentInHook'] = "true";
  }

  if( isset( $assoc_args['openhookurl'] ) ) {
    $server_edits['OpenHookUrl'] = $assoc_args['openhookurl'];
  }

  if( isset( $assoc_args['postfirstopenonly'] ) ) {
    $server_edits['PostFirstOpenOnly'] = "true";
  }

  if( isset( $assoc_args['trackopens'] ) ) {
    $server_edits['TrackOpens'] = "true";
  }

  if( isset( $assoc_args['clickhookurl'] ) ) {
    $server_edits['ClickHookUrl'] = $assoc_args['clickhookurl'];
  }

  if( isset( $assoc_args['inbounddomain'] ) ) {
    $server_edits['InboundDomain'] = $assoc_args['inbounddomain'];
  }

  if( isset( $assoc_args['inboundspamthreshold'] ) && is_int( $assoc_args['inboundspamthreshold'] ) ) {
    $server_edits['InboundSpamThreshold'] = $assoc_args['inboundspamthreshold'];
  }

  if( isset( $assoc_args['enablesmtpapierrorhooks'] ) ) {
    $server_edits['EnableSmtpApiErrorHooks'] = "true";
  }

  // Uses server color if present.
  if ( isset( $assoc_args['servercolor'] ) ) {

    switch ($assoc_args['servercolor']) {
      case "purple":
        $server_edits['Color'] = "purple";
        break;

      case "blue":
        $server_edits['Color'] = "blue";
        break;

      case 'turqoise':
        $server_edits['Color'] = "turqoise";
        break;

      case 'green':
        $server_edits['Color'] = "green";
        break;

      case 'red':
        $server_edits['Color'] = "red";
        break;

      case 'yellow':
        $server_edits['Color'] = "yellow";
        break;

      case 'grey':
        $server_edits['Color'] = "grey";
        break;

      case 'orange':
        $server_edits['Color'] = "orange";
        break;

      default:
        WP_CLI::Warning("Possible values for colors are purple, blue, turquoise, green, red, yellow, grey, or orange.");
        break;
    }
  }

  // Uses track links value if present.
  if ( isset($assoc_args['tracklinks']) ) {
    switch ($assoc_args['tracklinks']) {

      case 'None':
        $server_edits['TrackLinks'] = "None";
        break;

      case 'HtmlAndText':
        $server_edits['TrackLinks'] = "HtmlAndText";
        break;

      case 'HtmlOnly':
        $server_edits['TrackLinks'] = "HtmlOnly";
        break;

      case 'TextOnly':
        $server_edits['TrackLinks'] = "TextOnly";
        break;

      default:
        WP_CLI::Warning("Possible values for track links option are None, HtmlAndText, HtmlOnly, or TextOnly. Setting to None.");
        break;
    }
  }

  $options = array(
    'method' => 'PUT',
    'headers' => $headers,
    'body' => json_encode( $server_edits )
  );

  var_dump($options);

  // Creates new server.
  $response = wp_remote_request($url, $options);

  $progress->tick();

  // If all goes well, display the edited server's details using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();

    $response['body'] = json_decode($response['body']);

    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  } elseif ( is_array( $response ) && false == (wp_remote_retrieve_response_code( $response ) == 200 )) {

    $progress->finish();

    WP_CLI::warning('Server edit failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  } else {
    $progress->finish();
    WP_CLI::warning('Server edit failed.');
  }
};

/**********************************************
***************** Domains API *****************
**********************************************/

# Registers a custom WP-CLI command for retrieving
# a list of domains.
#
# Example usage:
#
# $ wp postmarkgetdomains <account api token>
#
# Success: {
# {
#  "TotalCount": 2,
#  "Domains": [
#    {
#      "Name": "wildbit.com",
#      "SPFVerified": true,
#      "DKIMVerified": true,
#      "WeakDKIM": false,
#      "ReturnPathDomainVerified": false,
#      "ID": 36735
#    },
#    {
#      "Name": "example.com",
#      "SPFVerified": true,
#      "DKIMVerified": true,
#      "WeakDKIM": false,
#      "ReturnPathDomainVerified": true,
#      "ID": 81605
#    }
#  ]
# }

/**
 * Retrieves list of domains.
 * <accountkey>
 * : Account API token.
 *
 * [--count=<count>]
 * : Number of domains to return per request. Max 500.
 *
 * [--offset=<offset>]
 * : Number of domains to skip.
 *
 * @when after_wp_load
 */
$getdomains = function($args, $assoc_args) {

  $url = "https://api.postmarkapp.com/domains";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting delivery stats', $count );

  $progress->tick();

  if(isset($args[0])) {
    $headers = array('X-Postmark-Account-Token' => $args[0]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    // TODO: add example of setting api token using wp cli
    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');
  }

  $progress->tick();

  // Checks for a count parameter and uses it if set.
  if ( isset($assoc_args['count']) && is_int($assoc_args['count']) && $assoc_args['count'] < 501 && $assoc_args['count'] > 0) {
    $url .= "?count={$assoc_args['count']}";

  // Uses 500 for default count if count not specified.
  } elseif (!isset($assoc_args['count'])) {
    $url .= "?count=500";
  }

  // Checks for an offset parameter and uses it if set.
  if ( isset($assoc_args['offset']) && is_int($assoc_args['offset'])) {
    $url .= "&offset={$assoc_args['offset']}";

  // Uses 0 for default offset if offset not specified.
  } elseif (!isset($assoc_args['offset'])) {
    $url .= "&offset=0";
  }

  $progress->tick();

  // Retrieves delivery stats
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved domains using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {

    $progress->finish();
    WP_CLI::warning('Domains list retrieval failed.');
    $response['body'] = json_decode($response['body']);
    WP_CLI::warning('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  } else {
    $progress->finish();
    WP_CLI::warning('Domains list retrieval failed.');
  }
};

# Registers a custom WP-CLI command for retrieving
# a domain's details.
#
# Example usage:
#
# $ wp postmarkgetdomain <account api token> <domainid>
#
# Success: {
#  "Name": "wildbit.com",
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa;
#   p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa;
# p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
#  "DKIMRevokedHost": "",
#  "DKIMRevokedTextValue": "",
#  "SafeToRemoveRevokedKeyFromDNS": false,
#  "DKIMUpdateStatus": "Pending",
#  "ReturnPathDomain": "pmbounces.wildbit.com",
#  "ReturnPathDomainVerified": false,
#  "ReturnPathDomainCNAMEValue": "pm.mtasv.net",
#  "ID": 36735
# }

/**
 * Retrieves details for a domain.
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of domain to retrieve details for.
 *
 * @when after_wp_load
 */
$getdomain = function($args) {

  $url = "https://api.postmarkapp.com/domains/" . $args[1];

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting domain ID ' . $args[1], $count );

  $progress->tick();

  if(isset($args[0])) {
    $headers = array('X-Postmark-Account-Token' => $args[0]);
    $headers['Accept'] = 'application/json';
    $progress->tick();
  } else {
    WP_CLI::error('You need to specify an account api token.');
  }

  $progress->tick();

  // Retrieves domain information.
  $response = wp_remote_get($url, array( 'headers' => $headers));
  $progress->tick();

  // If all goes well, display the retrieved domain using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode( $response['body'] );
    WP_CLI::success( json_encode( $response['body'], JSON_PRETTY_PRINT ) );
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();
    WP_CLI::warning( 'Domain retrieval failed.' );
    $response['body'] = json_decode( $response['body'] );
    WP_CLI::warning('Postmark API Response: ' . json_encode( $response['body'], JSON_PRETTY_PRINT ));
  } else {
    $progress->finish();
    WP_CLI::warning( 'Domain retrieval failed.' );
  }
};

# Registers a custom WP-CLI command for retrieving
# a domain's details.
#
# Example usage:
#
# $ wp postmarkgetdomain <account api token> <domainid>
#
# Success: {
#  "Name": "wildbit.com",
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa;
#   p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa;
# p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
#  "DKIMRevokedHost": "",
#  "DKIMRevokedTextValue": "",
#  "SafeToRemoveRevokedKeyFromDNS": false,
#  "DKIMUpdateStatus": "Pending",
#  "ReturnPathDomain": "pmbounces.wildbit.com",
#  "ReturnPathDomainVerified": false,
#  "ReturnPathDomainCNAMEValue": "pm.mtasv.net",
#  "ID": 36735
# }

/**
 * Creates a domain.
 *
 * <accountkey>
 * : Account API token.
 *
 * <name>
 * : Name of the new domain - e.g. example.com.
 *
 * [--returnpathdomain=<returnpathdomain>]
 * : A custom value for the Return-Path domain. It is an optional field, but it
 * must be a subdomain of your From Email domain and must have a CNAME record
 * that points to pm.mtasv.net.
 *
 * @when after_wp_load
 */
$createdomain = function( $args, $assoc_args ) {

  $url = "https://api.postmarkapp.com/domains";

  if ( !isset( $args[1] ) ) {
    WP_CLI::error( 'You need to specify the new domain.' );
  }

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Creating new domain ' . $args[1], $count );

  $progress->tick();

  if( isset( $args[0] ) ) {

    // Sets the headers.
    $headers = array('X-Postmark-Account-Token' => $args[0]);
    $headers['Accept'] = 'application/json';
    $headers['Content-Type'] = 'application/json';

    // Checks for ReturnPathDomain.
    if ( isset( $assoc_args['returnpathdomain'] ) ) {

      $rpdomain = $assoc_args['returnpathdomain'];

    } else {
      // Creates domain without setting a custom return-path domain.
      $rpdomain = "";
    }

    $progress->tick();

  } else {
    WP_CLI::error( 'You need to specify an account api token for this command.' );
  }

  $progress->tick();

  $options = array(
    'method' => 'POST',
    'blocking' => true,
    'headers' => $headers,
    'body' => json_encode( array( 'Name' => $args[1], 'ReturnPathDomain' => $rpdomain )),
  );

  // Calls Domains API to create new domain.
  $response = wp_remote_post($url, $options);

  $progress->tick();

  // If all goes well, display the created domain information using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();

    $response['body'] = json_decode( $response['body'] );
    WP_CLI::success( json_encode( $response['body'], JSON_PRETTY_PRINT ) );

  } elseif ( is_array( $response ) && false == ( wp_remote_retrieve_response_code( $response ) == 200 ) ) {

    $progress->finish();

    WP_CLI::warning( 'Domain creation failed.' );

    $response['body'] = json_decode( $response['body'] );

    WP_CLI::warning('Postmark API Response: ' . json_encode( $response['body'], JSON_PRETTY_PRINT ));

  } else {

    $progress->finish();

    WP_CLI::error( 'Domain creation failed.' );

  }
};

# Registers a custom WP-CLI command for editing
# a domain's custom return-path.
#
# Example usage:
#
# $ wp postmarkeditdomain <account api token> <domainid> <returnpathdomain>
#
# Success: {
#  "Name": "wildbit.com",
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa;
#   p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa;
# p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
#  "DKIMRevokedHost": "",
#  "DKIMRevokedTextValue": "",
#  "SafeToRemoveRevokedKeyFromDNS": false,
#  "DKIMUpdateStatus": "Pending",
#  "ReturnPathDomain": "pmbounces.wildbit.com",
#  "ReturnPathDomainVerified": false,
#  "ReturnPathDomainCNAMEValue": "pm.mtasv.net",
#  "ID": 36735
# }

/**
 * Edits an existing domain.
 *
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of the domain to edit.
 *
 * <returnpathdomain>
 * : A custom value for the Return-Path domain. It is an optional field, but it
 * must be a subdomain of your From Email domain and must have a CNAME record
 * that points to pm.mtasv.net.
 *
 * Example:
 * wp postmarkeditdomain "<yourtoken>" <domainid> pmbounces.yourdomain.com
 *
 * @when after_wp_load
 */
$editdomain = function ($args) {

  if ( !isset( $args[0] ) ) {
    WP_CLI::error( 'You need to specify an account API token for this command.' );
  }

  if ( !isset( $args[1] ) ) {
    WP_CLI::error( 'You need to specify the domain to edit, using the domain\'s ID.' );
  }

  if ( !isset( $args[2] ) ) {
    WP_CLI::error( 'You need to specify the new return path domain for this domain you are editing.' );

  } else {
    $url = "https://api.postmarkapp.com/domains/{$args[1]}";
  }

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Editing domain ID ' . $args[1], $count );

  $progress->tick();

  // Sets the headers.
  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';
  $headers['Content-Type'] = 'application/json';

  $progress->tick();

  $options = array(
    'method'     => 'PUT',

    'body'       => json_encode(
      array( "ReturnPathDomain" => "{$args[2]}" )
    ),

    'headers'    => $headers
  );

  $response = wp_remote_request( $url, $options );

  $progress->tick();

  // If all goes well, display the edited domain using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();

    WP_CLI::warning('Domain edit failed.');

    $response['body'] = json_decode($response['body']);

    WP_CLI::error('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning('Domain edit failed.');
  }
};

/**
 * Deletes an existing domain.
 *
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of the domain to edit.
 *
 * Example:
 * wp postmarkdeletedomain "<yourtoken>" <domainid>
 *
 * @when after_wp_load
 */
$deletedomain = function ($args) {

  if ( !isset( $args[0] ) ) {
    WP_CLI::error( 'You need to specify an account API token for this command.' );
  }

  $url = "https://api.postmarkapp.com/domains/{$args[1]}";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Deleting domain ID ' . $args[1], $count );

  $progress->tick();

  // Sets the headers.
  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';

  $progress->tick();

  $options = array(
    'method'     => 'DELETE',

    'headers'    => $headers
  );

  $response = wp_remote_request( $url, $options );

  $progress->tick();

  // If all goes well, display the edited domain using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();

    WP_CLI::warning('Domain deletion failed.');

    $response['body'] = json_decode($response['body']);

    WP_CLI::error('Postmark API Response: ' . json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning('Domain deletion failed.');
  }
};

# Registers a custom WP-CLI command for verifying DKIM for a domain.
#
# Example usage:
#
# $ wp postmarkverifydkim <account api token> <domainid>
#
# Success: {
#  "Name": "wildbit.com",
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa;
#   p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa;
# p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
#  "DKIMRevokedHost": "",
#  "DKIMRevokedTextValue": "",
#  "SafeToRemoveRevokedKeyFromDNS": false,
#  "DKIMUpdateStatus": "Pending",
#  "ReturnPathDomain": "pmbounces.wildbit.com",
#  "ReturnPathDomainVerified": false,
#  "ReturnPathDomainCNAMEValue": "pm.mtasv.net",
#  "ID": 36735
# }

/**
 * Verifies DKIM for an existing domain.
 *
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of the domain to verify DKIM for.
 *
 * @when after_wp_load
 */
$verifydkim = function ($args) {

  if ( !isset( $args[0] ) ) {
    WP_CLI::error( 'You need to specify an account API token for this command.' );
  }

  if ( !isset( $args[1] ) ) {
    WP_CLI::error( 'You need to specify the domain to edit, using the domain\'s ID.' );
  }

  $url = "https://api.postmarkapp.com/domains/{$args[1]}/verifyDkim";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Verifying DKIM for domain ID ' . $args[1], $count );

  $progress->tick();

  // Sets the headers.
  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';
  $headers['Content-Type'] = 'application/json';

  $progress->tick();

  $options = array(
    'method'     => 'PUT',

    // Body cannot be null for this call.
    'body'       => ' ',

    'headers'    => $headers
  );

  $response = wp_remote_request( $url, $options );

  $progress->tick();

  // If all goes well, display the edited domain using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();

    WP_CLI::warning('DKIM verification attempt failed.');

    $response['body'] = json_decode( $response['body'] );

    WP_CLI::error( 'Postmark API Response: ' . json_encode( $response['body'], JSON_PRETTY_PRINT ) );

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning( 'DKIM verification attempt failed.' );
  }
};

# Registers a custom WP-CLI command for verifying DKIM for a domain.
#
# Example usage:
#
# $ wp postmarkverifydkim <account api token> <domainid>
#
# Success: {
#  "Name": "wildbit.com",
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa;
#   p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa;
# p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
#  "DKIMRevokedHost": "",
#  "DKIMRevokedTextValue": "",
#  "SafeToRemoveRevokedKeyFromDNS": false,
#  "DKIMUpdateStatus": "Pending",
#  "ReturnPathDomain": "pmbounces.wildbit.com",
#  "ReturnPathDomainVerified": false,
#  "ReturnPathDomainCNAMEValue": "pm.mtasv.net",
#  "ID": 36735
# }

/**
 * Verifies DKIM for an existing domain.
 *
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of the domain to verify DKIM for.
 *
 * @when after_wp_load
 */
$verifyreturnpath = function ($args) {

  if ( !isset( $args[0] ) ) {
    WP_CLI::error( 'You need to specify an account API token for this command.' );
  }

  if ( !isset( $args[1] ) ) {
    WP_CLI::error( 'You need to specify the domain to edit, using the domain\'s ID.' );
  }

  $url = "https://api.postmarkapp.com/domains/{$args[1]}/verifyReturnPath";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Verifying Return-Path for domain ID ' . $args[1], $count );

  $progress->tick();

  // Sets the headers.
  $headers = array('X-Postmark-Account-Token' => $args[0]);
  $headers['Accept'] = 'application/json';
  $headers['Content-Type'] = 'application/json';

  $progress->tick();

  $options = array(
    'method'     => 'PUT',

    // Body cannot be null for this call.
    'body'       => ' ',

    'headers'    => $headers
  );

  $response = wp_remote_request( $url, $options );

  $progress->tick();

  // If all goes well, display the edited domain using the CLI.

  // Success
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {
    $progress->finish();
    $response['body'] = json_decode($response['body']);
    WP_CLI::success(json_encode($response['body'], JSON_PRETTY_PRINT));

  // Error returned from API
  } elseif (is_array( $response ) && !(wp_remote_retrieve_response_code( $response ) == 200)) {
    $progress->finish();

    WP_CLI::warning('Return-Path verification failed.');

    $response['body'] = json_decode( $response['body'] );

    WP_CLI::error( 'Postmark API Response: ' . json_encode( $response['body'], JSON_PRETTY_PRINT ) );

  // Error in making API call.
  } else {
    $progress->finish();
    WP_CLI::warning( 'Return-Path verification attempt failed.' );
  }
};

# Registers a custom WP-CLI command rotating
# a domain's DKIM key.
#
# Example usage:
#
# $ wp postmarkrotatedkim <account api token> <domainid>
#
# Success: {
#  "Name": "wildbit.com",
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa;
#   p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa;
# p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
#  "DKIMRevokedHost": "",
#  "DKIMRevokedTextValue": "",
#  "SafeToRemoveRevokedKeyFromDNS": false,
#  "DKIMUpdateStatus": "Pending",
#  "ReturnPathDomain": "pmbounces.wildbit.com",
#  "ReturnPathDomainVerified": false,
#  "ReturnPathDomainCNAMEValue": "pm.mtasv.net",
#  "ID": 36735
# }

/**
 * Creates a new DKIM key to replace your current key. Until the new DNS
 * entries are confirmed, the pending values will be in DKIMPendingHost and
 * DKIMPendingTextValue fields. After the new DKIM value is verified in DNS,
 * the pending values will migrate to DKIMTextValue and DKIMPendingTextValue
 * and Postmark will begin to sign emails with the new DKIM key.
 *
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of the domain to rotate DKIM for.
 *
 * @when after_wp_load
 */
$rotatedkim = function( $args ) {

  $url = "https://api.postmarkapp.com/domains/{$args[1]}/rotatedkim";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Rotating DKIM for Domain ID ' . $args[1], $count );

  $progress->tick();

  if( isset( $args[0] ) ) {

    // Sets the headers.
    $headers = array('X-Postmark-Account-Token' => $args[0]);
    $headers['Accept'] = 'application/json';
    $headers['Content-Type'] = 'application/json';

    $progress->tick();

  } else {
    WP_CLI::error( 'You need to specify an account api token for this command.' );
  }

  $progress->tick();

  $options = array(
    'method' => 'POST',
    'blocking' => true,
    'headers' => $headers,

    // Body must be present
    'body' => ' ',
  );

  // Calls Domains API to create new domain.
  $response = wp_remote_post($url, $options);

  $progress->tick();

  // If all goes well, display the created domain information using the CLI.
  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();

    $response['body'] = json_decode( $response['body'] );
    WP_CLI::success( json_encode( $response['body'], JSON_PRETTY_PRINT ) );

  } elseif ( is_array( $response ) && false == ( wp_remote_retrieve_response_code( $response ) == 200 ) ) {

    $progress->finish();

    WP_CLI::warning( 'DKIM rotation failed.' );

    $response['body'] = json_decode( $response['body'] );

    WP_CLI::warning('Postmark API Response: ' . json_encode( $response['body'], JSON_PRETTY_PRINT ));

  } else {

    $progress->finish();

    WP_CLI::error( 'DKIM rotation failed.' );

  }
};

// Makes sure Postmark exists before adding wp cli command to
// send a test email.
if( class_exists( 'Postmark_Mail' ) ) {
  // Adds Postmark API commands to WP CLI.
  // Send a test email.
  WP_CLI::add_command( 'postmarksendtestemail', $sendtestemail);

  //Bounces API.
  WP_CLI::add_command( 'postmarkgetdeliverystats', $getdeliverystats );
  WP_CLI::add_command( 'postmarkgetbounces', $getbounces );
  WP_CLI::add_command( 'postmarkgetbounce', $getbounce );
  WP_CLI::add_command( 'postmarkgetbouncedump', $getbouncedump );
  WP_CLI::add_command( 'postmarkactivatebounce', $activatebounce );
  WP_CLI::add_command( 'postmarkgetbouncedtags', $getbouncedtags );

  // Servers API
  WP_CLI::add_command( 'postmarkgetserver', $getserver );
  WP_CLI::add_command( 'postmarkcreateserver', $createserver );
  WP_CLI::add_command( 'postmarkeditserver', $editserver );

  // Domains API.
  WP_CLI::add_command( 'postmarkgetdomains', $getdomains );
  WP_CLI::add_command( 'postmarkgetdomain', $getdomain );
  WP_CLI::add_command( 'postmarkcreatedomain', $createdomain );
  WP_CLI::add_command( 'postmarkeditdomain', $editdomain );
  WP_CLI::add_command( 'postmarkdeletedomain', $deletedomain );
  WP_CLI::add_command( 'postmarkverifydkim', $verifydkim );
  WP_CLI::add_command( 'postmarkverifyreturnpath', $verifyreturnpath );
  WP_CLI::add_command( 'postmarkrotatedkim', $rotatedkim );


} else {
  WP_CLI::error( 'Postmark_Mail class not found. Make sure plugin is activated before using Postmark WP CLI commands.' );
}
