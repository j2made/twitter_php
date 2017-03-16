<?php

/**
 * Twitter API to json
 *
 * Connect to Twitter API using TwitterOAuth by Abraham Williams
 * Cache data to json file, return account info and tweets as array
 * that can easily be used in a php file.
 *
 *
 * Default `get_tweets_json()` return format:
 * Array
 *   (
 *     [acct] => TWITTER_HANDLE
 *     [acct_link] => https://twitter.com/TWITTER_HANDLE
 *     [tweets] => Array
 *       (
 *         [0] => Array
 *           (
 *             [desc] => TWEET CONTENT (STRING)
 *             [time] => TWEET TIME (STRING)
 *           )
 *
 *         ...
 *       )
 *     [error] =>
 *   );
 *
 * @link https://github.com/abraham/twitteroauth
 * @version  1.0.0
 *
 */



/**
 * Require Twitter OAuth, setup namespace
 *
 * ---------------------------------
 * =--> Update path as required <--=
 * ---------------------------------
 *
 * @link https://github.com/abraham/twitteroauth
 *
 */
require_once("lib/twitteroauth/autoload.php");

use Abraham\TwitterOAuth\TwitterOAuth;



/**
 * Set Default Timezone if not already set
 *
 * @since  1.0.0
 */
if ( date_default_timezone_get() === ini_get('date.timezone') ) {

  date_default_timezone_set('America/New_York');

}



/**
 * Get a URL via file_get_contents, fallback to cURL
 * via https://gist.github.com/mrclay/1271106
 *
 * @param  $url  string  url to fetch
 * @since  1.0.0
 */
function Fetch_Url( $url ) {

  $allowUrlFopen = preg_match('/1|yes|on|true/i', ini_get('allow_url_fopen'));

  if ( $allowUrlFopen ) {

      return file_get_contents($url);

  } elseif ( function_exists('curl_init') ) {

      $c = curl_init($url);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
      $contents = curl_exec($c);
      curl_close($c);

      if ( is_string($contents) ) {

          return $contents;

      }

  }

  return false;

}



/**
 * Get latest tweets
 * Use cached tweets or make a new API call
 *
 * @param  string  $handle            Twitter User Handle
 * @param  string  $consumer_key      Consumer Key from Twitter app
 * @param  string  $consumer_sec      Comsumer Secrete from Twitter app
 * @param  string  $access_token      Access Token from Twitter app
 * @param  string  $access_token_sec  Access Token Secret from Twitter app
 * @param  int     $cache_lifespan    Cache file lifespan, in milliseconds. Defualt: 60s
 * @param  string  $cache_file        Path to cache file
 * @param  integer $tweets_to_display How many tweets to display
 * @param  boolean $ignore_replies    Whether or not to display replies. Default: true
 * @param  string  $date_format       Default PHP Date format. Default: 'M jS, Y'
 * @param  boolean $relative_time     Whether or not the time should be displayed relative to current time
 *
 * @return array                      Array with user values and tweets
 */
function get_tweets_json(

  /**
   * Function Parameters
   *
   */
  $handle            = 'TWITTER_HANDLE',
  $consumer_key      = 'CONSUMER_KEY',
  $consumer_sec      = 'CONSUMER_SECRET',
  $access_token      = 'ACCESS_TOKEN',
  $access_token_sec  = 'ACCESS_TOKEN_SECRET',
  $cache_lifespan    = 60 * 3,
  $cache_file        = __DIR__ . '/tweets.json',
  $tweets_to_display = 5,
  $ignore_replies    = true,
  $date_format       = 'M jS, Y',
  $relative_time     = true

) {

  $cache_file_exists = file_exists( $cache_file );

  // Determine when the cache was last updated.
  $cache_file_mod = ( $cache_file_exists ) ? filemtime( $cache_file ) : 0;

  // Show cached version of tweets, if it's less than $cache_lifespan.
  if ( $cache_file_exists && time() - $cache_lifespan < $cache_file_mod ) {

    return json_decode(  Fetch_Url( $cache_file ), true );

  } else {

    // Cache file not found, or old. Get new tweets
    $connection = new TwitterOAuth( $consumer_key, $consumer_sec, $access_token, $access_token_sec );

    if ( ! $connection ) {

      $tweet_data = array(
        'error' => 'No connection.'
      );

      return $tweet_data;

    }

    $get_tweets = $connection->get("statuses/user_timeline", ["count" => $tweets_to_display, "exclude_replies" => $ignore_replies]);

    // If there aren't any tweets
    if ( count( $get_tweets ) === 0 ) {

      $tweet_data = array(
        'error' => 'No tweets to display.'
      );

      return $tweet_data;

    } else {

      // The master array to return
      $tweet_data = array(
        'acct' => $handle,
        'acct_link' => 'https://twitter.com/' . $handle,
        'tweets' => array(),
        'error' => false
      );

      // Format each tweet and add it to the master array
      foreach ( $get_tweets as $tweet ) {

        // Format Description
        $tweet_desc = $tweet->text;
        $tweet_desc = preg_replace( '/(https?:\/\/\S+)/', '<a href="\1">\1</a>', $tweet_desc );
        $tweet_desc = preg_replace( '/(^|\s)@(\w+)/', '\1@<a href="http://twitter.com/\2">\2</a>', $tweet_desc );
        $tweet_desc = preg_replace( '/(^|\s)#(\w+)/', '\1#<a href="http://search.twitter.com/search?q=%23\2">\2</a>', $tweet_desc );

        // Format Time
        $tweet_time = strtotime($tweet->created_at);

        if ( $relative_time ) {
          // Format time relative to current time
          $current_time = time();
          $time_diff    = abs($current_time - $tweet_time);

          switch ( $time_diff ) {

            case ( $time_diff < 60 ):

              $display_time = $time_diff.' seconds ago';
              break;

            case ( $time_diff >= 60 && $time_diff < 3600 ):

              $min          = floor($time_diff/60);
              $display_time = $min.' minutes ago';
              break;

            case ( $time_diff >= 3600 && $time_diff < 86400 ):

              $hour          = floor($time_diff/3600);
              $display_time  = $hour.' hr';
              $display_time .= $hour > 1 ? 's' : '';
              $display_time .= ' ago';
              break;

            default:

              $display_time = date( $date_format,$tweet_time );
              break;

          }

        } else {

          // Format to match default time
          $display_time = date( $date_format,$tweet_time );

        }

        // Add formatted tweet to tweet data
        $tweet_data['tweets'][] = array(
          'desc' => $tweet_desc,
          'time' => $display_time
        );

      } // end foreach

      $json = json_encode( $tweet_data, JSON_PRETTY_PRINT );

      // Generate a new cache file, saving $tweet_data as json
      if ( $cache_file_exists ) {

        $file = fopen( $cache_file, 'a' );
        fwrite( $file, $json );
        fclose( $file );

      } else {

        $file = fopen( $cache_file, 'w' );
        fwrite( $file, $json );
        fclose( $file );

      }

      return $tweet_data;

    } // end if ( count( $get_tweets ) )

  } // end if ( time() - $cache_lifespan < $cache_file_created ) | else

}

