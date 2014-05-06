<?php
/*
Plugin Name: WP Enqueuer
Plugin URI: https://github.com/rxnlabs/wp-enqueuer
Description: WordPress plugin to enqueue commonly used javascript and css
Version: 1.0a
Author: De'Yonte W.
Author URI: http://rxnlabs.com
License:
Copyright 2014 De'Yonte W.
 
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.
 
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
 
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/

// File Security Check
if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) && basename( __FILE__ ) == basename( $_SERVER['SCRIPT_FILENAME'] ) ) {
  die ( 'You do not have sufficient permissions to access this page!' );
}

// Only create an instance of the plugin if it doesn't already exists in GLOBALS
if( ! array_key_exists( 'wp-enqueuer', $GLOBALS ) ) {
 
  class WP_Enqueuer {
    
    private $script_file;

    public function __construct() {
      $this->script_file = "wp-enqueuer-scripts.json";

      //load WordPress hooks
      $this->admin_hooks();
    }

    public function admin_hooks(){
      add_action( 'admin_menu', 'register_my_custom_menu_page' );
      //http://codex.wordpress.org/add_menu_page
      add_menu_page('WP Enqueuer Settings', 'WP Enqueuer', 'manage_options', __FILE__, array(&$this,'settings_page') , get_stylesheet_directory_uri('stylesheet_directory')."/images/media-button-other.gif");
    }

    public function install(){

    }


    public function get_assets_file(){
      $assets_file = file_get_contents($this->get_assets_file_path());

      if( $assets_file != false )
        $assets_file = json_decode($assets_file);
      else
        $assets_file = false;

      return $assets_file;
    }

    public function get_assets_file_path(){
      if ( (substr(plugin_dir_path( __FILE__ ), -1) == '/') OR (substr(plugin_dir_path( __FILE__ ), -1) == '\\') ){
        $plugin_path = substr_replace(plugin_dir_path( __FILE__ ),"",-1);
      }
      else{
        $plugin_path = plugin_dir_path( __FILE__ );
      }

      $assets_file_path = $plugin_path.DIRECTORY_SEPARATOR.$this->script_file;

      return $assets_file_path;
    }
    
    public function get_post_types(){
      $public_post_types = get_post_types(array('public'=>'true'),'names');

      // post types to not enqueue scripts for
      $remove_posts = array();

      // remove certain post types
      foreach ($public_post_types as $value) {
        foreach ($remove_posts as $remove) {
          if( array_key_exists($remove, $public_post_types))
            unset($public_post_types[$remove]);
        }
      }

      return $public_post_types;
    }

    public function set_enqueue(){
      // don't autosave
      if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

      // if user can't manage options
      if( !current_user_can( 'manage_options' ) ) return;

      // if our nonce isn't there, or we can't verify it, bail
        if( !isset( $_POST['wp_enqueuer_nonce'] ) || !wp_verify_nonce( $_POST['wp_enqueuer_nonce'], 'wp_enqueuer_nonce' ) ) return; 

      // save the scripts to be enqueued based on the post type
      $post_types = $this->get_post_types();
      $set_enqueue = array();
      foreach( $post_types as $key=>$value ){
        $wp_enqueuer = $_POST['wp_enqueuer_'.$key];
        foreach( $wp_enqueuer as $enqueue ){

        }

        if( !empty($wp_enqueuer) AND !is_null($wp_enqueuer) )
          $result = update_option( 'wp_enqueuer_'.$key, $wp_enqueuer);
        
        $set_enqueue[$key] = $result;
      }

      return $set_enqueue;
    }

    public function settings_page(){
      ?>
      <p><?php _e( 'Select scripts to enqueue' );?></p>
      <?php
    }
  }
   
  // Store a reference to the plugin in GLOBALS so that our unit tests can access it
  $GLOBALS['wp-enqueuer'] = new WP_Enqueuer();
     
}
