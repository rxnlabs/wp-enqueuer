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
      $this->front_hooks();
    }

    public function admin_hooks(){
      add_action( 'admin_menu', array(&$this,'settings_page') );
      add_action( 'admin_enqueue_scripts', array(&$this,'admin_enqueue') );
      add_action( 'admin_init', array( $this, 'set_enqueue' ) );
    }

    public function front_hooks(){
      add_action( 'wp_enqueue_scripts', array(&$this,'front_enqueue_scripts') );
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
      if( $_GET['page'] === "wp-enqueuer-page.php" ){
        if( isset($_POST) AND !empty($_POST) AND array_filter($_POST) != false AND is_admin() AND check_admin_referer('wp_enqueuer_save_settings','wp_enqueuer_settings') ){
          
          // don't autosave
          if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

          // if user can't manage options
          if( !current_user_can( 'manage_options' ) ) return;

          $dependencies = json_decode(json_encode($this->get_assets_file()),true);
          // save the scripts to be enqueued based on the post type
          $post_types = $this->get_post_types();
          if( !empty($post_types) ){
            $set_enqueue = array();
            foreach( $post_types as $key=>$value ){
              $wp_enqueuer = $_POST['wp_enqueuer_'.$key];
              $scripts_load = null;
              if( !empty($wp_enqueuer) ){
                $scripts_load = array();
                $count =0;
                foreach( $wp_enqueuer as $enqueue ){
                  $dependency_found = false;

                  // search the scripts file to see if the selected scripts have any dependencies
                  $script_key = $this->array_search_r($enqueue,$dependencies['scripts']);
                  if( $script_key != false ){

                    //if script has dependencies, add those to array
                    if( !empty($dependencies['scripts'][$script_key[0]]['deps']) ){
                      $script_name = $enqueue;
                      $enqueue = array($script_name=>'');
                      foreach( $dependencies['scripts'][$script_key[0]]['deps'] as $dep ){
                        $enqueue[$script_name][] = $dep['name'];
                      }
                      $dependency_found = true;
                    }

                    if( $dependency_found === false AND !empty($dependencies['styles'][$script_key[0]]['deps']) ){
                      foreach( $dependencies['styles'][$script_key[0]]['deps'] as $dep ){
                        $enqueue[$script_name][] = $dep['name'];
                      }
                    }
                  }

                  $scripts_load[] = $enqueue;
                  
                  $count++;
                } 
                
                $set_enqueue[$key] = $result;
              }
              $result = update_option( 'wp_enqueuer_'.$key, $scripts_load);
            }
          }

          return $set_enqueue;
        }
      }
    }

    public function get_enqueue($post_type = null){
      $post_types = $this->get_post_types();

      $scripts = false;
      if( !empty($post_types) AND is_null($post_type) ){
        $scripts = array();
        foreach( $post_types as $key=>$value ){
          if ( get_option( 'wp_enqueuer_'.$key ) !== false ) {
            $scripts['wp_enqueuer_'.$key] = get_option( 'wp_enqueuer_'.$key );
          }
        }
      }elseif( !is_null($post_type) ){
        if ( get_option( 'wp_enqueuer_'.$post_type ) !== false ) {
          $scripts['wp_enqueuer_'.$post_type] = get_option( 'wp_enqueuer_'.$post_type );
        }
      }

      return $scripts;
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

    public function in_array_r($needle, $haystack, $strict = false) {
      foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
            return true;
        }
      }

      return false;
    }

    public function array_search_r( $needle, $haystack, $strict=false, $path=array() ){
      if( !is_array($haystack) ) {
          return false;
      }
   
      foreach( $haystack as $key => $val ) {
        if( is_array($val) && $subPath = $this->array_search_r($needle, $val, $strict, $path) ) {
            $path = array_merge($path, array($key), $subPath);
            return $path;
        } elseif( (!$strict && $val == $needle) || ($strict && $val === $needle) ) {
            $path[] = $key;
            return $path;
        }
      }
      return false;
    }

    public function front_enqueue_scripts(){
      $post_types = $this->get_post_types();

      if( !empty($post_types) ){
        $current_post_type = get_post_type();
        $scripts = $this->get_enqueue($current_post_type);

        if( !empty($scripts) ){
          // keep an array of loaded scripts
          $loaded_scripts = array();
          // load the script loader file
          $assets = json_decode(json_encode($this->get_assets_file()),true);

          foreach ( $scripts['wp_enqueuer_'.$current_post_type] as $key => $script) {
            // check to see if script has any dependencies
            if( is_array($script) ){
              //get the first key of the array (the name of the script to load)
              reset($script);
              $script_name = key($script);
              //key dependencies in array
              $dependencies = array();
              foreach( $script[$script_name] as $dep ){
                $dependency_found = false;

                foreach( $assets['scripts'] as $key=>$asset ){
                  if( $dep === $asset['name'] ){
                    if( $assets['scripts'][$key]['host'] === "local" ){
                      $source = plugin_dir_url( __FILE__ ).'assets/js/'.$assets['scripts'][$key]['uri'];
                    }else{
                      $source = $assets['scripts'][$key]['uri'];
                    }

                    $version = $assets['scripts'][$key]['version'];
                    $handle = $dep;

                    // register dependency
                    wp_register_script( $handle, $source, array(),$version,false );
                    // enqueue dependency
                    wp_enqueue_script( $handle );
                    $loaded_scripts[] = $handle;
                    $dependency_found = true;
                    break;
                  }
                }

                if( $dependency_found === false ){
                  foreach( $assets['styles'] as $key=>$asset ){
                    if( $dep === $asset['name'] ){
                      if( $assets['styles'][$key]['host'] === "local" ){
                        $source = plugin_dir_url( __FILE__ ).'assets/css/'.$assets['styles'][$asset_key[0]]['uri'];
                      }else{
                        $source = $assets['styles'][$key]['uri'];
                      }

                      $version = $assets['styles'][$key]['version'];
                      $handle = $dep;
                      // register dependency
                      wp_register_style( $handle, $source, '',$version );
                      // enqueue dependency
                      wp_enqueue_style( $handle );
                      $loaded_scripts[] = $handle;
                      $dependency_found = true;
                      break;
                    }
                  }
                }

                $dependencies[] = $dep;
              }
              $script = $script_name;
            }

            $script_found = false;
            foreach( $assets['scripts'] as $key=>$asset ){
              if( $script === $asset['name'] ){
                if( $asset['host'] === "local" ){
                  $source = plugin_dir_url( __FILE__ ).'assets/js/'.$asset['uri'];
                }else{
                  $source = $asset['uri'];
                }

                $version = $asset['version'];
                $handle = $script;

                // register script
                if( !isset($dependencies) )
                  $dependencies = array();

                wp_register_script( $handle, $source, $dependencies,$version,false );
                // enqueue script
                wp_enqueue_script( $handle );
                $loaded_scripts[] = $handle;
                $script_found = true;
                break;
              }
            }

            if( $script_found === false ){
              foreach( $assets['styles'] as $key=>$asset ){
                if( $script === $asset['name'] ){
                  if( $asset['host'] === "local" ){
                    $source = plugin_dir_url( __FILE__ ).'assets/css/'.$asset['uri'];
                  }else{
                    $source = $asset['uri'];
                  }

                  $version = $asset['version'];
                  $handle = $script;
                  if( !isset($dependencies) )
                    $dependencies = array();
                  // register style
                  wp_register_style( $handle, $source, $dependencies,$version );
                  // enqueue style
                  wp_enqueue_style( $handle );
                  $loaded_scripts[] = $handle;
                  $script_found = true;
                  break;
                }
              }
            }
          }
        }
      }
    }
  }
   
  // Store a reference to the plugin in GLOBALS so that our unit tests can access it
  $GLOBALS['wp-enqueuer'] = new WP_Enqueuer();
     
}
