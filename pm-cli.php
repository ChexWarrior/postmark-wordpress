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

  if ( !check_server_token_is_set( $postmark_settings ) ) {
    return;
  }

  $url = "https://api.postmarkapp.com/deliverystats";

  // Retrieves delivery stats
  $response = postmark_api_call('get', $url, null, null, $postmark_settings);

  postmark_handle_response($response, $progress);

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

  if ( !check_server_token_is_set($postmark_settings ) ) {
    return;
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

  // Retrieve bounces
  $response = postmark_api_call('get', $url, null, null, $postmark_settings);

  postmark_handle_response($response, $progress);

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

  if ( !check_server_token_is_set($postmark_settings ) ) {
    return;
  }

  // Makes sure bounce id provided in command.
  if ( isset($args['0'])) {
    $bounceid = $args['0'];
    $url = "https://api.postmarkapp.com/bounces/" . $args['0'];
  } else {
    WP_CLI::error('Specify a bounce id.');
  }

  // Retrieve the bounce
  $response = postmark_api_call( 'get', $url, null, null, $postmark_settings );

  postmark_handle_response( $response, $progress );

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

  if ( !check_server_token_is_set($postmark_settings ) ) {
    return;
  }

  // Makes sure bounce id provided in command.
  if ( isset($args['0'])) {
    $bounceid = $args['0'];
    $url = "https://api.postmarkapp.com/bounces/" . $args['0'] . "/dump";
  } else {
    WP_CLI::error('Specify a bounce id.');
  }

  // Retrieve the bounce dump
  $response = postmark_api_call( 'get', $url, null, null, $postmark_settings );

  postmark_handle_response( $response, $progress );

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

  if ( !check_server_token_is_set($postmark_settings ) ) {
    return;
  }

  // Makes sure bounce id provided in command.
  if ( isset($args['0'])) {
    $bounceid = $args['0'];
    $url = "https://api.postmarkapp.com/bounces/" . $args['0'] . "/activate";
  } else {
    WP_CLI::error('Specify a bounce id to reactivate.');
  }

  // Activates the bounce - needs to include an empty body.
  $response = postmark_api_call('put', $url, ' ', null, $postmark_settings );

  postmark_handle_response( $response, $progress );

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
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting bounced tags', $count );

  if ( !check_server_token_is_set($postmark_settings ) ) {
    return;
  }

  $url = "https://api.postmarkapp.com/bounces/tags";

  // Retrieves the bounced tags.
  $response = postmark_api_call('get', $url, null, null, $postmark_settings );

  postmark_handle_response( $response, $progress );

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
$getserver = function( $args, $assoc_args ) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Getting Server ID {$args[1]}", $count );

  $url = "https://api.postmarkapp.com/servers/{$args[1]}";

  // Retrieves server details.
  $response = postmark_api_call('get', $url, null, $args[0], null);

  postmark_handle_response($response, $progress);

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
 * [--smtpapiactivated]
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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Creating new server -  {$args[1]}", $count );

  $url = "https://api.postmarkapp.com/servers";

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

  // Creates new server.
  $response = postmark_api_call('post', $url, json_encode( $new_server ), $args[0], null);

  postmark_handle_response($response, $progress);

};

# Registers a custom WP-CLI command for editing
# a server.
#
# Example usage:
#
# $ wp postmarkeditserver <account api token> <serverid>
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
 * [--rawemailenabled=<rawemailenabled>]
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
 * [--includebouncecontentinhook=<includebouncecontentinhook>]
 * : Include bounce content in webhook.
 *
 * [--openhookurl=<openhookurl>]
 * : URL to POST to every time an open event occurs.
 *
 * [--postfirstopenonly=<postfirstopenonly>]
 * : If set to true, only the first open by a particular recipient will initiate
 * the open webhook. Any subsequent opens of the same email by the same
 * recipient will not initiate the webhook.
 *
 * [--trackopens=<trackopens>]
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
 * [--enablesmtpapierrorhooks=<enablesmtpapierrorhooks>]
 * : Specifies whether or not SMTP API Errors will be included with bounce
 * webhooks.
 *
 * @when after_wp_load
 */
$editserver = function( $args, $assoc_args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Editing server ID {$args[1]}", $count );

  $url = "https://api.postmarkapp.com/servers/{$args[1]}";

  $server_edits = array();

  if( isset( $assoc_args['name'] ) ) {
    $server_edits['Name'] = $assoc_args['name'];
  }

  if( isset( $assoc_args['smtpapiactivated'] ) ) {
    $server_edits['SmtpApiActivated'] = $assoc_args['smtpapiactivated'];
  }

  if( isset( $assoc_args['rawemailenabled'] ) ) {
    $server_edits['RawEmailEnabled'] = $assoc_args['rawemailenabled'];
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
    $server_edits['IncludeBounceContentInHook'] = $assoc_args['includebouncecontentinhook'];
  }

  if( isset( $assoc_args['openhookurl'] ) ) {
    $server_edits['OpenHookUrl'] = $assoc_args['openhookurl'];
  }

  if( isset( $assoc_args['postfirstopenonly'] ) ) {
    $server_edits['PostFirstOpenOnly'] = $assoc_args['postfirstopenonly'];
  }

  if( isset( $assoc_args['trackopens'] ) ) {
    $server_edits['TrackOpens'] = $assoc_args['trackopens'];
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
    $server_edits['EnableSmtpApiErrorHooks'] = $assoc_args['enablesmtpapierrorhooks'];
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

  // Edits the server.
  $response = postmark_api_call( 'put', $url, json_encode( $server_edits ), $args[0], null );

  postmark_handle_response( $response, $progress );

};

# Registers a custom WP-CLI command for retrieving
# all servers.
#
# Example usage:
#
# $ wp postmarkgetservers <account api token>
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
 * Retrieves a list of all servers.
 *
 * <accountkey>
 * : Account API token.
 *
 * [--count=<count>]
 * : Number of servers to retrieve.
 *
 * [--offset=<offset>]
 * : Number of servers to skip.
 *
 * [--name=<name>]
 * : Filter by a specific server name. Note that this is a string search, so
 * MyServer will match MyServer, MyServer Production, and MyServer Test.
 *
 * @when after_wp_load
 */
$getservers = function($args, $assoc_args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Getting servers.", $count );

  $url = "https://api.postmarkapp.com/servers/";

  if( isset( $assoc_args['count'] )) {
    $url .= "?count=" . $assoc_args['count'];
  } else {
    $url .= "?count=500";
  }

  if( isset( $assoc_args['offset'] )) {
    $url .= "&offset=" . $assoc_args['offset'];
  } else {
    $url .= "&offset=0";
  }

  if( isset( $assoc_args['name'] )) {
    $url .= "&name=" . $assoc_args['name'];
  }

  // Retrieves server details.
  $response = postmark_api_call('get', $url, null, $args[0], null);

  postmark_handle_response( $response, $progress );

};

# Registers a custom WP-CLI command for deleting
# a server.
#
# Example usage:
#
# $ wp postmarkdeleteserver <account api token> <serverid>
#
# Success: {
#  "ErrorCode": 0,
#  "Message": "Server Production Server 2 removed."
# }

/**
 * Deletes an existing server.
 *
 * <accountkey>
 * : Account API token.
 *
 * <serverid>
 * : ID of server to delete.
 *
 * @when after_wp_load
 */
$deleteserver = function( $args, $assoc_args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Deleting server ID {$args[1]}", $count );

  $url = "https://api.postmarkapp.com/servers/{$args[1]}";

  // Deletes the server.
  $response = postmark_api_call( 'delete', $url, null, $args[0], null );

  postmark_handle_response( $response, $progress );

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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting domains.', $count );

  $url = "https://api.postmarkapp.com/domains";

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

  // Retrieves domains.
  $response = postmark_api_call( 'get', $url, null, $args[0], null );

  postmark_handle_response( $response, $progress );

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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting domain ID ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/domains/" . $args[1];

  // Retrieves domain information.
  $response = postmark_api_call( 'get', $url, null, $args[0], null );

  postmark_handle_response( $response, $progress );

};

# Registers a custom WP-CLI command for creating a new domain.
#
# Example usage:
#
# $ wp postmarkcreatedomain <account api token> <name>
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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Creating new domain ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/domains";

  // Checks for ReturnPathDomain.
  if ( isset( $assoc_args['returnpathdomain'] ) ) {

    $rpdomain = $assoc_args['returnpathdomain'];

  } else {
    // Creates domain without setting a custom return-path domain.
    $rpdomain = "";
  }

  $body = json_encode( array( 'Name' => $args[1], 'ReturnPathDomain' => $rpdomain ));

  // Calls Domains API to create new domain.
  $response = postmark_api_call( 'post', $url, $body, $args[0], null );

  postmark_handle_response( $response, $progress );

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

  $url = "https://api.postmarkapp.com/domains/{$args[1]}";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Editing domain ID ' . $args[1], $count );


  $body = json_encode(
      array( "ReturnPathDomain" => "{$args[2]}" )
  );

  $response = postmark_api_call( 'put', $url, $body, $args[0], null );

  postmark_handle_response( $response, $progress );

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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Deleting domain ID ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/domains/{$args[1]}";

  $response = postmark_api_call( 'delete', $url, null, $args[0], null );

  postmark_handle_response( $response, $progress );

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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Verifying DKIM for domain ID ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/domains/{$args[1]}/verifyDkim";

  $response = postmark_api_call( 'put', $url, ' ', $args[0], null );

  postmark_handle_response( $response, $progress );

};

# Registers a custom WP-CLI command for verifying a custom return-path for a
# domain.
#
# Example usage:
#
# $ wp postmarkverifyreturnpath <account api token> <domainid>
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
 * Verifies a custom return-path for an existing domain.
 *
 * <accountkey>
 * : Account API token.
 *
 * <domainid>
 * : ID of the domain to verify return-path for.
 *
 * @when after_wp_load
 */
$verifyreturnpath = function ($args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Verifying Return-Path for domain ID ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/domains/{$args[1]}/verifyReturnPath";

  $response = postmark_api_call( 'put', $url, ' ', $args[0], null );

  postmark_handle_response( $response, $progress );
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

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Rotating DKIM for Domain ID ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/domains/{$args[1]}/rotatedkim";

  // Rotate DKIM key.
  $response = postmark_api_call( 'post', $url, ' ', $args[0], null );

  postmark_handle_response( $response, $progress );

};

/**********************************************
************ Sender signatures API ************
**********************************************/

# Registers a custom WP-CLI command for retrieving
# a list of sender signatures.
#
# Example usage:
#
# $ wp postmarkgetsignatures <account api token>
#
# Success: {
#  "TotalCount": 2,
#  "SenderSignatures": [
#    {
#      "Domain": "wildbit.com",
#      "EmailAddress": "jp@wildbit.com",
#      "ReplyToEmailAddress": "info@wildbit.com",
#      "Name": "JP Toto",
#      "Confirmed": true,
#      "ID": 36735
#    },
#    {
#      "Domain": "example.com",
#      "EmailAddress": "jp@example.com",
#      "ReplyToEmailAddress": "",
#      "Name": "JP Toto",
#      "Confirmed": true,
#      "ID": 81605
#    }
#  ]
# }

/**
 * Gets a list of sender signatures containing brief details associated with
 * your account.
 *
 * <accountkey>
 * : Account API token.
 *
 * [--count=<count>]
 * : Number of signatures to return per request. Max 500.
 *
 * [--offset=<offset>]
 * : Number of signatures to skip.
 *
 * @when after_wp_load
 */
$getsignatures = function($args, $assoc_args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Getting sender signatures', $count );

  $url = "https://api.postmarkapp.com/senders";

  // Checks for a count parameter and uses it if set.
  if ( isset($assoc_args['count']) && is_int($assoc_args['count']) && $assoc_args['count'] < 501 && $assoc_args['count'] > 0) {
    $url .= "?count={$assoc_args['count']}";

  // Uses 500 for default count if count not specified.
  } else {
    $url .= "?count=500";
  }

  // Checks for an offset parameter and uses it if set.
  if ( isset($assoc_args['offset']) && is_int($assoc_args['offset'])) {
    $url .= "&offset={$assoc_args['offset']}";

  // Uses 0 for default offset if offset not specified.
  } else {
    $url .= "&offset=0";
  }

  // Retrieves signatures list
  $response = postmark_api_call('get', $url, null, $args[0]);

  postmark_handle_response($response, $progress);

};

# Registers a custom WP-CLI command for retrieving
# a list of sender signatures.
#
# Example usage:
#
# $ wp postmarkgetsignatures <account api token>
#
# Success: {
#  "Domain": "wildbit.com",
#  "EmailAddress": "jp@wildbit.com",
#  "ReplyToEmailAddress": "info@wildbit.com",
#  "Name": "JP Toto",
#  "Confirmed": true,
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013.pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228.pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
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
 * Gets all the details for a specific sender signature.
 *
 * <accountkey>
 * : Account API token.
 *
 * <signatureid>
 * : Sender signature ID.
 *
 * @when after_wp_load
 */
$getsignature = function($args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Getting sender signature ID {$args[1]}" , $count );

  $url = "https://api.postmarkapp.com/senders/{$args[1]}";

  // Retrieves signatures list
  $response = postmark_api_call( 'get', $url, null, $args[0], null);

  postmark_handle_response( $response, $progress );

};

# Registers a custom WP-CLI command for creating
# a sender signature.
#
# Example usage:
#
# $ wp postmarkcreatesignature <account api token> <fromemail> <name>
#
# Success: {
#  "Domain": "wildbit.com",
#  "EmailAddress": "jp@wildbit.com",
#  "ReplyToEmailAddress": "info@wildbit.com",
#  "Name": "JP Toto",
#  "Confirmed": true,
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013.pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228.pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
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
 * Creates a sender signature.
 *
 * <accountkey>
 * : Account API token.
 *
 * <fromemail>
 * : From email associated with sender signature.
 *
 * <name>
 * : From name associated with sender signature.
 *
 * [--replytoemail=<replytoemail>]
 * : Override for reply-to address.
 *
 * [--returnpathdomain=<returnpathdomain>]
 * : A custom value for the Return-Path domain. It is an optional field, but it
 * must be a subdomain of your From Email domain and must have a CNAME record
 * that points to pm.mtasv.net.
 *
 * @when after_wp_load
 */
$createsignature = function( $args, $assoc_args ) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Creating new signature ' . $args[1], $count );

  $url = "https://api.postmarkapp.com/senders";

  // Checks for ReplyToEmail.
  if ( isset( $assoc_args['replytoemail'] ) ) {

    $replytoemail = $assoc_args['replytoemail'];

  } else {
    // Creates domain without setting a replytoemail.
    $replytoemail = "";
  }

  // Checks for ReturnPathDomain.
  if ( isset( $assoc_args['returnpathdomain'] ) ) {

    $rpdomain = $assoc_args['returnpathdomain'];

  } else {
    // Creates domain without setting a custom return-path domain.
    $rpdomain = "";
  }

  $body = json_encode( array( 'FromEmail' => $args[1], 'Name' => $args[2] ) );

  // Calls Domains API to create new domain.
  $response = postmark_api_call( 'post', $url, $body, $args[0], null);

  postmark_handle_response( $response, $progress );

};

# Registers a custom WP-CLI command for editing
# a sender signature.
#
# Example usage:
#
# $ wp postmarkeditsignature <account api token> <signatureid> <name>
#
# Success: {
#  "Domain": "wildbit.com",
#  "EmailAddress": "jp@wildbit.com",
#  "ReplyToEmailAddress": "info@wildbit.com",
#  "Name": "JP Toto",
#  "Confirmed": true,
#  "SPFVerified": true,
#  "SPFHost": "wildbit.com",
#  "SPFTextValue": "v=spf1 a mx include:spf.mtasv.net ~all",
#  "DKIMVerified": false,
#  "WeakDKIM": false,
#  "DKIMHost": "jan2013.pm._domainkey.wildbit.com",
#  "DKIMTextValue": "k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ...",
#  "DKIMPendingHost": "20131031155228.pm._domainkey.wildbit.com",
#  "DKIMPendingTextValue": "k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCFn...",
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
 * Edits an existing sender signature.
 *
 * <accountkey>
 * : Account API token.
 *
 * <signatureid>
 * : ID of the signature to edit.
 *
 * <name>
 * : From name associated with sender signature.
 *
 * [--replytoemail=<ReplyToEmail>]
 * : Override for reply-to address.
 *
 *  [--returnpathdomain=<returnpathdomain>]
 * : A custom value for the Return-Path domain. It is an optional field, but it
 * must be a subdomain of your From Email domain and must have a CNAME record
 * that points to pm.mtasv.net. For more information about this field, please
 * read our support page.
 *
 * Example:
 * wp postmarkeditsignature "<yourtoken>" 123456 "John Doe"
 *
 * @when after_wp_load
 */
$editsignature = function ($args, $assoc_args) {

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Editing signature ID {$args[1]}", $count );

  $url = "https://api.postmarkapp.com/senders/{$args[1]}";

  $body = array(
    "Name" => "{$args[2]}"
  );

  if ( isset( $assoc_args['replytoemail'] ) ) {
    $body['ReplyToEmail'] = $assoc_args['replytoemail'];
  }

  if ( isset( $assoc_args['returnpathdomain'] ) ) {
    $body['ReturnPathDomain'] = $assoc_args['returnpathdomain'];
  }

  $response = postmark_api_call( 'put', $url, json_encode($body), $args[0], null );

  postmark_handle_response( $response, $progress );

};

/**
 * Deletes an existing sender signature.
 *
 * <accountkey>
 * : Account API token.
 *
 * <signatureid>
 * : ID of the signature to delete.
 *
 * Example:
 * wp postmarkdeletesignature "<yourtoken>" <signatureid>
 *
 * @when after_wp_load
 */
$deletesignature = function ($args) {

  $url = "https://api.postmarkapp.com/senders/{$args[1]}";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( 'Deleting signature ID ' . $args[1], $count );

  $response = postmark_api_call('delete', $url, null, $args[0]);

  postmark_handle_response($response, $progress);

};

# Registers a custom WP-CLI command for resending
# a confirmation email for a sender signature.
#
# Example usage:
#
# $ wp postmarkresendconfirmation <account api token> <signatureid>
#
# Success: {
#  "ErrorCode": 0,
#  "Message": "Confirmation email for Sender Signature john.doe@example.com was
# re-sent."
# }

/**
 * Resends a confirmation email for a sender signature.
 *
 * <accountkey>
 * : Account API token.
 *
 * <signatureid>
 * : ID of sender signature.
 *
 * @when after_wp_load
 */
$resendconfirmation = function( $args ) {

  $url = "https://api.postmarkapp.com/senders/{$args[1]}/resend";

  // ticker
  $progress = \WP_CLI\Utils\make_progress_bar( "Resending confirmation email for sender signature ID {$args[1]}", $count );

  $body = '';

  // Calls Signatures API to resend confirmation email.
  $response = postmark_api_call('post', $url, $body, $args[0]);

  postmark_handle_response($response, $progress);

};

function check_server_token_is_set( $postmark_settings ) {

  if( !isset( $postmark_settings["api_key"] ) ) {

    WP_CLI::error('You need to set your Server API Token in the Postmark plugin settings.');

    return false;

  } else {

    return true;

  }

}

function postmark_api_call( $method, $url, $body, $account_token, $postmark_settings = null ) {

  if ( isset( $account_token ) ) {
    $headers = array('X-Postmark-Account-Token' => $account_token);
  } else {
    $headers = array('X-Postmark-Server-Token' => $postmark_settings["api_key"]);
  }

  switch ($method) {

    case "get":

      $headers["Accept"] = 'application/json';

      return wp_remote_get($url, array( 'headers' => $headers));

    case "post":

      $headers['Content-Type'] = 'application/json';

      $options = array(
        'method' => 'POST',
        'blocking' => true,
        'headers' => $headers,
        'body' => $body
      );

      return wp_remote_post($url, $options);

      case "put":
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';

        $options = array(
          'method' => 'PUT',
          'headers' => $headers,
          'body' => $body
        );

        return wp_remote_request($url, $options);

      case "delete":
        $headers['Accept'] = 'application/json';

        $options = array(
          'method' => 'DELETE',
          'headers' => $headers
        );

        return wp_remote_request($url, $options);
  }

}

function postmark_handle_response($response, $progress) {

  if ( is_array( $response ) && wp_remote_retrieve_response_code( $response ) == 200) {

    $progress->finish();

    $response['body'] = json_decode( $response['body'] );
    WP_CLI::success( json_encode( $response['body'], JSON_PRETTY_PRINT ) );

  } elseif ( is_array( $response ) && false == ( wp_remote_retrieve_response_code( $response ) == 200 ) ) {

    $progress->finish();

    WP_CLI::warning( 'Error occurred.' );

    $response['body'] = json_decode( $response['body'] );

    WP_CLI::error('Postmark API Response: ' . json_encode( $response['body'], JSON_PRETTY_PRINT ));

  } else {

    $progress->finish();

    WP_CLI::error( 'Error occurred.' );
  }
}

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
  WP_CLI::add_command( 'postmarkgetservers', $getservers );
  WP_CLI::add_command( 'postmarkdeleteserver', $deleteserver );

  // Domains API.
  WP_CLI::add_command( 'postmarkgetdomains', $getdomains );
  WP_CLI::add_command( 'postmarkgetdomain', $getdomain );
  WP_CLI::add_command( 'postmarkcreatedomain', $createdomain );
  WP_CLI::add_command( 'postmarkeditdomain', $editdomain );
  WP_CLI::add_command( 'postmarkdeletedomain', $deletedomain );
  WP_CLI::add_command( 'postmarkverifydkim', $verifydkim );
  WP_CLI::add_command( 'postmarkverifyreturnpath', $verifyreturnpath );
  WP_CLI::add_command( 'postmarkrotatedkim', $rotatedkim );

  // Signatures API
  WP_CLI::add_command( 'postmarkgetsignatures', $getsignatures );
  WP_CLI::add_command( 'postmarkgetsignature', $getsignature );
  WP_CLI::add_command( 'postmarkcreatesignature', $createsignature );
  WP_CLI::add_command( 'postmarkeditsignature', $editsignature );
  WP_CLI::add_command( 'postmarkdeletesignature', $deletesignature );
  WP_CLI::add_command( 'postmarkresendconfirmation', $resendconfirmation );

} else {
  WP_CLI::error( 'Postmark_Mail class not found. Make sure plugin is activated before using Postmark WP CLI commands.' );
}
