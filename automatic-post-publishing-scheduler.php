<?php
/*
Plugin Name: Automatic Post Publishing Scheduler
Plugin URI: http://www.willthewebmechanic.com
Description: Publishes posts according to a pre-defined schedule.  See 'Settings/Scheduler' on your WordPress dashboard.  The free version of this plugin allows a maximum of three defined time slots.  For unlimited time slots and to unlock premium features, please contact <a href="http://www.willthewebmechanic.com">Will the Web Mechanic</a>.
Version: 1.1
Author: Will Brubaker
Author URI: http://www.willthewebmechanic.com
License: GPLv3
*/
/*
    Automatic Post Publishing Scheduler WordPress plugin
    Copyright (C) 2013 Will Brubaker (Will the Web Mechanic)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @package PublishScheduler
 */
class Publish_Scheduler
{
 static private $wwm_plugin_values = array(
                                        'name' => 'PublishScheduler',
                                        'version' => '1.1', //hate using a string value here, but need it to hold non-numeric values
                                        'slug' => 'PublishScheduler',
                                        'dbversion' => '1.5',
                                        'supplementary' => array(//a place to put things in the future..
                                         )
                                        );
 /**
  * runs every time and adds the necessary action and filter hooks among other things that need to happen every time.
  */
 public function Publish_Scheduler()
 {

  add_action( 'init', array( &$this, 'init_plugin' ) );
  add_action( 'admin_menu', array( &$this, 'my_plugin_menu' ) );
  //assign time slots AJAX action
  add_action( 'wp_ajax_assign_time_slots', array( &$this, 'assign_time_slots' ) );
  add_filter( 'wp_insert_post_data', array( &$this, 'queue_post' ), 10, 2 );
  add_action( 'admin_enqueue_scripts', array( &$this, 'wwm_enqueue_admin_scripts' ) );
  if ( is_admin() ) {

   add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'plugin_manage_link' ), 10, 4 );

  }
 }

 public function init_plugin()
 {

   if ( ! get_option( 'publish_scheduler_options' ) ) {

    update_option(
        'publish_scheduler_options', array(

            'slots' => array(
                array( '09', '00', 'am' ),
                array( '10', '00', 'am' ),
                array( '11', '00', 'am' ),
            )
        )
    );
  }
 }

 public function wwm_enqueue_admin_scripts()
 {
     //load our js for the ajax call to 'Publish Now'
     wp_enqueue_style( 'jquery-ui-lightness', plugins_url( 'css/jquery-ui.min.css', __FILE__ ), array(), self::$wwm_plugin_values['version'] );      wp_enqueue_style( 'jquery-ui-dialog-min' );
     if ( strpos( $_SERVER['REQUEST_URI'], 'page=PublishSchedule.php' ) ) {
         wp_enqueue_script( 'scheduler-options-js', plugins_url( 'js/scheduleroptions.js', __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), self::$wwm_plugin_values['version'], true );
         wp_enqueue_style( 'scheduler-stylesheet', plugins_url( 'css/scheduler.css', __FILE__ ), null , self::$wwm_plugin_values['version'] );
     }
 }

 public function my_plugin_menu()
 {

     add_options_page( 'Scheduler Options', 'Scheduler', 'manage_options', 'PublishSchedule.php', array( $this, 'scheduler_options' ) );
 }

 public function scheduler_options()
 {
  $html_outputs = array();
  $db_up_to_date = ( get_option( 'wwm_pubscheduler_db_version' ) == self::$wwm_plugin_values['dbversion'] ? true : false );
  if ( ! $db_up_to_date ) {
      $time_slots = get_option( 'publish_scheduler_options' );
      $time_slots['days'] = ( empty( $time_slots ) ) ? array( 0,1,2,3,4,5,6 ) : $time_slots['days'];
      $time_slots['dates_denied'] = ( empty( $time_slots['dates_denied'] ) ) ? array() : $time_slots['dates_denied'];
      $time_slots['dates_allowed'] = ( empty( $time_slots['dates_allowed'] ) ) ? array() : $time_slots['dates_allowed'];

      foreach ( $time_slots['dates_denied'] as $key => $value ) {
      $now = strtotime( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) );
        if ( strtotime( $now ) > strtotime( $value ) || $value == "" ) {
         unset( $time_slots['dates_denied'][$key] );
        }
       }

      foreach ( $time_slots['dates_allowed'] as $key => $value ) {
      $now = strtotime( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) );
       if ( strtotime( $now ) > strtotime( $value ) || $value == "" ) {
        unset( $time_slots['dates_allowed'][$key] );
       }

       }
      update_option( 'publish_scheduler_options', $time_slots );
      update_option( 'wwm_pubscheduler_db_version', self::$wwm_plugin_values['dbversion'] );
  }

  //get the time slots that have already been defined:
  $time_slots = ( get_option( 'publish_scheduler_options' ) ? get_option( 'publish_scheduler_options' ) : array( 'dates_allowed' => array(), 'dates_denied' => array(), 'days' => array(), ) );
  $slots = $time_slots['slots'];
  $a = min( count( $slots ), 3 );
  $slots = array_slice( $slots, 0, $a );

      if ( empty( $time_slots['days'] ) ) {
          $time_slots['days'] = array( 0, 1, 2, 3, 4, 5, 6 );
          update_option( 'publish_scheduler_options', $time_slots );
      }
      if( empty( $time_slots['dates_allowed'] ) ) $time_slots['dates_allowed'] = array();
      if( empty( $time_slots['dates_denied'] ) ) $time_slots['dates_denied'] = array();
  $enabled_days = $time_slots['days'];
  $html = null;
  $i = 0;
  //store them in a human readable format for later use
  foreach ( $slots as $slot ) {

      $html .= '<div class="slot_input"><span class="label"><label for="hh[' . $i . ']" >slot ' . ( $i + 1 ) . ' : </label></span>' . "\n";
      $html .= '<input type="text" class="hour_input" id="hh[' . $i . ']" value="' . $slot[0] . '" name="hh[' . $i . ']" size="2" maxlength="2" autocomplete="off">' . "\n";
      $html .= ' :' . "\n";
      $html .= '<input type="text" id="mn[' . $i . ']" value="' . $slot[1] . '" name="mn[' . $i . ']" class="mn_input" size="2" maxlength="2" autocomplete="off">' . "\n";
      $html .= '  <select name="ampm[' . $i . ']" class="ampm_sel">' . "\n";
      $html .= '      <option value="am"';
              if ( $slot[2] == 'am' ) $html .= ' selected="selected"';
      $html .= '>am</option>';
      $html .= '      <option value="pm"';
              if ( $slot[2] == 'pm' ) $html .= ' selected="selected"';
      $html .= '>pm</option>';
      $html .= "</select><br /></div>\n";

      $i++;
      }
  ?>

  <div class="wrap">
      <div id="overlay"><span class="preloader"></span></div>
      <div id="icon-options-general" class="icon32"><br /></div>
      <h2>Set Scheduler Options:</h2>
      <p>
      <div class="updated">
       <p>
        <h4>The maximum number of slots allowed in the free version of this plugin is 3.  For unlimited slots and other premium features, please upgrade to the premium version of this plugin. <a href="http://www.willthwebmechanic.com">Will the Web Mechanic</a></h4>
        <ol>Premium features include:
         <li>Unlimited number of defined time slots</li>
         <li>Schedule override via a 'Publish Now' link on the posts page</li>
         <li>Schedule override via editing done in 'Quick Edit' mode</li>
         <li>Exclude days of the week (weekends, etc) from your publishing schedule</li>
         <li>Explicity include or exclude dates from your publishing schedule (holidays, etc)</li>
         <li>Premium Support</li>
        </ol>
       </p>
       <p>

        Input the number of time slots required.  If the number entered is less than the existing number, slots will be removed from the bottom up.  Changes will not be saved until committed using the 'Assign time slots' form below.
       </p>
      </div>
      <form id="set_time_slots">
      <input type="hidden" name="action" value="set_time_slots" />
      <input id="existing_count" type="hidden" name="existing_count" value="" />
      <label for="time_slots">Number of time slots required:<br />
      <input id="time_slots" type="text" name="time_slots" size="3" maxlength="3" /></label>
      <br /><br />
      <input type="submit" class="button button-primary" value="submit" />
      </form>
      </p>
      <hr />
      <div class="updated">
      <p>
      Use the form below to put times into your time slots.  There is no validation done prior to submission, invalid values will be discarded on processing.  Ordering will also be done during processing.  Changes will not take effect until the 'submit' button is pressed.
      </p>
      </div>
      <p>
      <h4>Assign time slots:</h4><br />
      <form id="assign_time_slots">
      <input type="hidden" name="action" value="assign_time_slots" />
      <?php
      echo $html;
      ?>
      <div id="new_time_slots"></div>
      <br /><br />
      <input type="submit" class="button button-primary" value="submit" />
      </form>
      </p>


  </div>

  <?php
 }

 /**
  * this is the meat and potatoes of this plugin.  it publishes posts based on the defined publishing schedule.
  * @param  array $data    an array of post data
  * @param  array $postarr an array of post arguments
  * @return array the modified $data array
  * @since 1.0
  */
 public function queue_post( $data, $postarr )
 {

  date_default_timezone_set( 'UTC' );
  $current_time = current_time( 'timestamp' );//time();
  $tz = get_option( 'timezone_string' );

  //A few instances where there is nothing to do, so just return the $data array.
  if( $data['post_type'] != 'post' ) return $data;
  if( $data['post_status'] == 'draft' ) return $data;
  if( $postarr['original_post_status'] == 'publish' || $postarr['post_status'] == 'auto-draft' || $postarr['post_status'] == 'trash' ) return $data; //nothing to do here.
  if( $_POST['save'] == 'Update' && $data['post_status'] != 'future') return $data;
  $publish_time = null;
  $available_time_slots = array();
  $dates_allowed = array();
  $dates_denied = array();
  $phptz_holder = date_default_timezone_get();
  //force 'seconds' to zero

  if ( isset( $data['post_date'] ) ) {

      $post_date = date_i18n( 'Y-m-d H:i', strtotime( $data['post_date'] ) );
      $post_date_gmt = gmdate( 'Y-m-d H:i', strtotime( $data['post_date'] ) );
  }

  //check if the time sent is in the past.  If so, return $data.
  $x = strtotime( $post_date );
  $y = strtotime( date_i18n( 'Y-m-d H:i', $current_time ) );//the seconds have been stripped from $x, so they need to be stripped here too.

  if ( $x < $y && current_user_can( 'edit_others_posts' ) ) {

      $data['post_status'] = 'publish';
       return $data;
  }

  //o.k., I have access to $_POST here....
  //If I manually schedule a post I have original publish set to 'Publish' and publish set to Schedule
  //If I click Publish  both are set to Publish.
  //post varibles mm jj aa hh mn are also worthwhile...
  //yes, we can make a time slot out of that that was asked for.  sweet!
  //an auto scheduled post has publish set for both variables i am looking at
  //original publish is UPdate - publish seems to have gone missing.

  //So...
  if ( $_POST['publish'] == 'Schedule' ) {
   //set current time to the time it asked for...
   $current_time = strtotime( $_POST['aa'] . '-' . $_POST['mm'] . '-' . $_POST['jj'] . ' ' . $_POST['hh'] . ':' . $_POST['mn'] );

  }
  //at this point, anyone who has permission to override a scheduled slot has.
  //now to check if the author is trying to alter their own time slot.

  if ($_POST['original_post_status']  == 'future' ) {
      //the time that was asked for is:
      $asked_for_time = strtotime( $_POST['aa'] . '-' . $_POST['mm'] . '-' . $_POST['jj'] . ' ' . $_POST['hh'] . ':' . $_POST['mn'] );
      $original_time_slot = strtotime( $_POST['hidden_aa'] . '-' . $_POST['hidden_mm'] . '-' . $_POST['hidden_jj'] . ' ' . $_POST['hidden_hh'] . ':' . $_POST['hidden_mn'] );
          if( $original_time_slot == $asked_for_time ) return $data;
          elseif ( $asked_for_time < $original_time_slot ) {//nope, earlier time slot is not yours.
               $post_date = date_i18n( 'Y-m-d H:i:s', $original_time_slot );
               $post_date_gmt = date_i18n( 'Y-m-d H:i:s', $original_time_slot, true );
               $post_date_gmt = date_i18n( 'Y-m-d H:i:s', strtotime( $original_time_slot . ' ' . get_option( 'timezone_string' ) ), true );
               $data['post_status'] = 'future';
               $data['post_date'] = $post_date;
               $data['post_date_gmt'] = $post_date_gmt;

               return $data;

          } else {
              $current_time = $asked_for_time;//o.k., you can have a slot further in the future.
          }

  }

  //get our time slots
  $time_slots = get_option( 'publish_scheduler_options');
  $dates_allowed = ( ! empty( $time_slots['dates_allowed'] ) ? $time_slots['dates_allowed'] : array() );
  $dates_denied = ( ! empty( $time_slots['dates_denied'] ) ? $time_slots['dates_denied'] : array() );

  $enabled_days = $time_slots['days'];
  $slots = $time_slots['slots'];

  //build an array of explicitly allowed time slots
  foreach ($dates_allowed as $allow) {
      foreach ($slots as $slot) {
      $x = strtotime( $allow . ' ' . $slot[0] . ':' . $slot[1] . ' ' . $slot[2] );
      $allowed_time_slots[] = $x;
      }
  }
  if( ! is_array( $allowed_time_slots ) ) {
      $allowed_time_slots = array();
  }
  $full_time_slots = array();
  //add the denied dates/times to the full_time_slots array:
  foreach ($dates_denied as $deny) {
      foreach ($slots as $slot) {
      $full_time_slots[] = strtotime( $deny . ' ' . $slot[0] . ':' . $slot[1] . ' ' . $slot[2] );

      }
  }
  foreach ($slots as $slot) {

      $ymd = date_i18n( 'Y-m-d', $current_time );
      $stamp = strtotime( $ymd . ' ' . $slot[0] . ':' . $slot[1] . ' ' . $slot[2] );

      $defined_time_slots[] = $stamp;
      if ($stamp >= $current_time) { //only looking for slots available in the future
          //and, it's only available if it's NOT on one of our days AND not 'denied'
          $dow = date_i18n( 'w', $stamp );//this is a numeric representation of the day of week contained in our timestamp.
          if ( !in_array( $dow, $enabled_days ) ) {//this particular day of the week is excluded
              if ( in_array( $stamp, $allowed_time_slots ) ) {//but this timestamp is explicitly allowed
              $available_time_slots[] = $stamp;//then it is available
              }
          } else {//the day of week is ok to publish on
              if ( !in_array( $stamp, $full_time_slots) ) {//this particular timestamp is not explicitly denied
                  $available_time_slots[] = $stamp;//so it is available
                  }
          }
      }
  }

  //get all the posts that are currently scheduled

  $args = array(
          'post_type' => 'post',
          'post_status' => 'future',
          'posts_per_page' => -1,
          'orderby' => 'date',
          'order' => 'ASC'
      );
  wp_reset_query();
  $my_query = new WP_Query($args);

  if ( $my_query->have_posts() ) {
      while ( $my_query->have_posts() ) {
          $my_query->the_post();
          $value = strtotime( get_the_time( 'Y-m-d g:i:s a') );
          //all I really need here is an array of time slots to check which ones are full
          $full_time_slots[] = $value;
          if ( ( $key = array_search( $value, $available_time_slots ) ) !== false ) {
              unset( $available_time_slots[$key] );
              }
          if ( ( $key = array_search( $value, $allowed_time_slots ) ) !== false ) {
              unset( $allowed_time_slots[$key] );
              }
      }
  }
  wp_reset_query();

  if( empty( $full_time_slots ) ) $full_time_slots[] = $current_time;

  //I now should have four arrays.  $available_time_slots, $full_time_slots $enabled_days and $defined_time_slots(which is a copy of the original available slots.
  //while available times slots is empty, loop through $defined_time_slots
  $i = 1; //start at 1 because that's how many days we want to add each time.
  while ( empty( $available_time_slots ) ) {

      foreach ($defined_time_slots as $x) {
              $y = $x + ( 60 * 60 * ( 24 * $i ) );
              $dow = date_i18n( 'w', $y );
              if ( ! in_array( $dow, $enabled_days ) ) {//the day is excluded
                  if ( in_array( $y, $allowed_time_slots ) ) {//it is explicitly allowed
                      $check[] = $y;
                  }
              } else {
                  if ( ! in_array( $y, $dates_denied ) ) {
                  $check[] = $y;
                  }
              }
          }
      $i++;
      foreach ( $check as $x ) {
              if ( ! in_array($x, $full_time_slots) && ( $x >= $current_time ) ) {
                          $available_time_slots[] = $x;
                          break;
                      }
              }
          }

  $publish_time = reset( $available_time_slots );
  //take our $publish_time which is a timestamp - convert it to a timedate with localization goodness for WP
  $post_date = date_i18n( 'Y-m-d H:i:s', $publish_time );
  //and also convert it to a gmt timedate.
  $post_date_gmt = date_i18n( 'Y-m-d H:i:s', strtotime( $post_date . ' ' . $tz ), true );

  //schedule post for that slot
  $data['post_status'] = 'future';
  $data['post_date'] = $post_date;
  $data['post_date_gmt'] = $post_date_gmt;

  //all done - return the modified $data array
  return $data;
 }

 //handle ajax call for assigning time slots:
 public function assign_time_slots()
 {
  $hours = $_POST['hh'];

  //first, validate the values in the 'hh' array.  If they pass, concatenate them with their counterparts
  //in the 'mn' and 'ampm' arrays, building a new array of timestamps provided that 'mn' is also valid.
  $i = 0;
  foreach ( $hours as $hour) {
          if ( is_numeric( $hour ) ) {
              if ( (int) $hour > -1 && (int) $hour < 13 ) {
                  if ( is_numeric( $_POST['mn'][$i] ) && (int) $_POST['mn'][$i] > -1 && (int) $_POST['mn'][$i] < 61 ) {
                      $timestamps[] = strtotime( $hour . ':' .  $_POST['mn'][$i] . $_POST['ampm'][$i] );
                  }

              }
          }
      $i++;
      }
  //the new array contains only *nix style timestamps, order it ascending
  asort( $timestamps );

  //populate an output array by breaking out the 'hh' 'mm' and 'am/pm' of the timestamps
  foreach ( $timestamps as $time) {
      $new_times[] = array( date( 'h',$time), date( 'i', $time), date( 'a',$time) );
  }

  //update our option
  $old_times = get_option( 'publish_scheduler_options' );
  $a = min( count( $new_times ), 3 );
  $old_times['slots'] = array_slice( $new_times, 0, $a );
  update_option( 'publish_scheduler_options', $old_times );

  //output the result back to the ajax callback function.
  echo json_encode( $old_times['slots'] );

  exit;
 }

  //add a link to the front of the actions list for this plugin.
 public function plugin_manage_link( $actions, $plugin_file, $plugin_data, $context)
 {

  return array_merge( array(
                     'Hire Me' => '<a href="http://www.willthewebmechanic.com">Hire Me</a>' ),
                     $actions );
 }
}
$var = new Publish_Scheduler;
