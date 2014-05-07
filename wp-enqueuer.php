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
    private $version;

    public function __construct() {
      $this->script_file = 'wp-enqueuer-scripts.json';
      $this->version = '1.0a';

      //load WordPress hooks
      $this->admin_hooks();
    }

    public function admin_hooks(){
      add_action( 'admin_menu', array(&$this,'settings_page') );
      add_action( 'admin_enqueue_scripts', array(&$this,'admin_enqueue') );
      add_action( 'admin_init', array(&$this,'set_enqueue') );
      //$this->set_enqueue();
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
      $remove_posts = array('attachment');

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
      add_options_page( 'WP Enqueuer Settings', 'WP Enqueuer', 'manage_options', 'wp-enqueuer-page.php', array(&$this,'load_settings_page') );
    }

    public function load_settings_page(){
      include( 'wp-enqueuer-page.php' );
    }

    public function admin_enqueue($hook){
      if( 'settings_page_wp-enqueuer-page' === $hook ){
        $this->admin_enqueue_scripts();
        $this->admin_enqueue_styles();
      }
    }

    public function admin_enqueue_scripts(){
      global $wp_scripts;
      // register scripts
      wp_register_script( 'bootstrap-collapse', plugin_dir_url( __FILE__ ).'library/js/bootstrap/js/bootstrap.min.js' , array('jquery'), '3.1.1' );
      wp_register_script( 'checkboxes.js', plugin_dir_url( __FILE__ ).'library/js/jquery.checkboxes-1.0.3.min.js' , array('jquery'), '1.0.3' );
      wp_register_script( 'footable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.min.js' , array('jquery'), '0.1.0' );
      wp_register_script( 'footable-sortable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.sortable.min.js' , array('jquery','footable'), '0.1.0' );
      wp_register_script( 'footable-filter', plugin_dir_url( __FILE__ ).'library/js/footable/footable.filter.min.js' , array('jquery','footable'), '0.1.0' );
      wp_register_script( 'footable-paginate', plugin_dir_url( __FILE__ ).'library/js/footable/footable.paginate.js' , array('jquery','footable'), '0.1.0' );
      wp_register_script( 'datatables', 'http://cdn.datatables.net/1.9.4/js/jquery.dataTables.min.js' , array('jquery'), '1.9.4' );
      wp_register_script( 'datatables-bootstrap',  plugin_dir_url( __FILE__ ).'library/js/dataTables.bootstrap.js' , array('jquery','datatables'), '1.1.1' );
      wp_register_script( 'datatables-responsive', plugin_dir_url( __FILE__ ).'library/js/datatables-responsive/files/1/js/datatables.responsive.js' , array('jquery','datatables'), '1.10.0' );
      wp_register_script( 'wp-enqueuer-scripts', plugin_dir_url( __FILE__ ).'library/js/wp-enqueuer-scripts.js' , array('jquery'), $this->version );

      // enqueue scripts
      wp_enqueue_script( 'bootstrap-collapse' );
      wp_enqueue_script( 'checkboxes.js' );
      wp_enqueue_script( 'footable' );
      wp_enqueue_script( 'footable-paginate' );
      wp_enqueue_script( 'footable-sortable' );
      wp_enqueue_script( 'footable-filter' );
      wp_enqueue_script( 'datatables' );
      //wp_enqueue_script( 'datatables-bootstrap' );
      wp_enqueue_script( 'datatables-responsive' );
      wp_enqueue_script( 'wp-enqueuer-scripts' );
    }

    public function admin_enqueue_styles(){
      // register styles
      wp_register_style( 'bootstrap-collapse', plugin_dir_url( __FILE__ ).'library/js/bootstrap/css/bootstrap.css' , false, '3.1.1' );
      wp_register_style( 'footable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.min.css' , false, '0.1.0' );
      wp_register_style( 'footable-sortable', plugin_dir_url( __FILE__ ).'library/js/footable/footable.sortable.min.css' , false, '0.1.0' );
      wp_register_style( 'datatables', plugin_dir_url( __FILE__ ).'library/js/datatables/css/jquery.dataTables.min.css' , false, '1.10.0' );
      wp_register_style( 'datatables-responsive', plugin_dir_url( __FILE__ ).'library/js/datatables-responsive/files/1/css/datatables.responsive.css' , false, '1.10.0' );

      // enqueue styles
      wp_enqueue_style( 'bootstrap-collapse' );
      wp_enqueue_style( 'footable' );
      wp_enqueue_style( 'footable-sortable' );
      wp_enqueue_style( 'datatables' );
      wp_enqueue_style( 'datatables-responsive' );
    }
  }
   
  // Store a reference to the plugin in GLOBALS so that our unit tests can access it
  $GLOBALS['wp-enqueuer'] = new WP_Enqueuer();
     
}
