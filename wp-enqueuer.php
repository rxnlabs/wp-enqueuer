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
    private $loaded_scripts;
    private $loaded_styles;

    public function __construct() {
      $this->script_file = 'wp-enqueuer-scripts.json';
      $this->version = '1.0a';
      //hold list of enqueued scripts so we don't enqueue them twice
      $this->loaded_scripts = array();
      $this->loaded_styles = array();

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
      add_action( 'wp_enqueue_scripts', array(&$this,'front_enqueue_assets') );
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

    public function set_enqueue_deps($dependencies,$script_name){
      $enqueue = array($script_name);
      foreach( $dependencies as $dep ){
        $enqueue[] = $dep['name'];
      }
      
      return $enqueue;
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
                foreach( $wp_enqueuer as $script_name ){
                  $dependency_found = false;

                  // search the scripts file to see if the selected scripts have any dependencies
                  $script_key = $this->array_search_r($script_name,$dependencies['scripts']);
                  if( $script_key != false ){

                    //if script has dependencies, add those to array
                    if( !empty($dependencies['scripts'][$script_key[0]]['deps']) ){
                      $script_name = $this->set_enqueue_deps($dependencies['scripts'][$script_key[0]]['deps'],$script_name);
                    }

                    if( !empty($dependencies['styles'][$script_key[0]]['deps']) ){
                      $script_name = $this->set_enqueue_deps($dependencies['styles'][$script_key[0]]['deps'],$script_name);
                    }
                  }

                  $scripts_load[] = $script_name;
                  
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

    public function get_enqueue_unique(){
      $post_types = $this->get_post_types();

      $scripts = false;
      if( !empty($post_types) AND is_null($post_type) ){
        $scripts = array();
        foreach( $post_types as $key=>$value ){
          if ( get_option( 'wp_enqueuer_'.$key ) !== false AND !empty(get_option( 'wp_enqueuer_'.$key )) ) {
            $scripts['wp_enqueuer_'.$key] = get_option( 'wp_enqueuer_'.$key );
            
            $count = 0;
            //loop through array to merge dependecines into one array
            foreach( $scripts['wp_enqueuer_'.$key] as $deps ){
              if( is_array($deps) ){
                foreach($deps as $dep){
                  $scripts['wp_enqueuer_'.$key][] = $dep;
                }
                //remove dependencies we just merged
                unset($scripts['wp_enqueuer_'.$key][$count]);
              }
              $count++;
            }

            //remove duplicate array values
            $scripts['wp_enqueuer_'.$key] = array_values(array_unique($scripts['wp_enqueuer_'.$key]));
          }
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

    public function front_enqueue_deps($dependencies,$type){
      $assets = json_decode(json_encode($this->get_assets_file()),true);

      foreach( $assets[$type] as $key=>$asset ){
        $deps = array();
        if( is_array($dependencies) ){
          foreach( $dependencies as $load_more_deps ){
            $this->front_enqueue_deps($load_more_deps,$type);
          }
          break;
        }elseif( $dependencies === $asset['name'] ){

          $handle = $dependencies;
          $version = $asset['version'];

          if( $type === 'scripts' ){
            $script_loader = array(
              'handle'=>$handle,
              'deps'=>$deps,
              'version'=>$version,
              );
            if( $asset['host'] === "local" )
              $source = plugin_dir_url( __FILE__ ).'assets/js/'.$asset['uri'];

            // check to see if the script has any styles that come with it
            if( isset($asset['styles']) ){
              foreach($asset['styles'] as $script_style ){
                $script_style_loader = array(
                  'handle'=>$handle,
                  'deps'=>array(),
                  'version'=>$version,
                  'source'=>$script_style['uri']
                  );
                $this->front_enqueue_asset('styles',$script_style_loader);
              }
            }
          }elseif( $type === 'styles' ){
            $script_loader = array(
              'handle'=>$handle,
              'deps'=>$deps,
              'version'=>$version,
              );

            if( $asset['host'] === "local" )
              $source = plugin_dir_url( __FILE__ ).'assets/css/'.$asset['uri'];

          }

          if( !isset($source) )
            $source = $asset['uri'];

          $script_loader['source'] = $source;

          $this->front_enqueue_asset($type,$script_loader);
          break;
        }

      }

    }

    public function front_enqueue_asset($type,$script_loader = array()){
      if( $type === "scripts" ){
        extract($script_loader);
        // register script
        wp_register_script( $handle, $source, $deps, $version, (isset($footer)?$footer:false) );
        // enqueue script
        wp_enqueue_script( $handle );
        $this->loaded_scripts[] = $handle;
      }elseif( $type === "styles" ){
        extract($script_loader);
        // register style
        wp_register_style( $handle, $source, $deps, $version, (isset($media)?$media:'') );
        // enqueue style
        wp_enqueue_style( $handle );
        $this->loaded_styles[] = $handle;
      }
    }

    public function front_enqueue_assets(){
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
              // remove the script that we want to load from array so we can enqueue after its dependencies have loaded
              $needed_script = $script[0];
              unset($script[0]);
              $script = array_values($script);
              foreach( $script as $dep ){
                // check to see if the dependencies have been loaded already
                if( !in_array($dep,$this->loaded_scripts) OR !in_array($dep,$this->loaded_styles) ){
                  $script_deps = $this->front_enqueue_deps($dep,'scripts');
                  $script_deps = $this->front_enqueue_deps($dep,'styles');
                }
              }
              // now set the script we wanted to load after we've loaded the dependencies
              $script = $needed_script;
            }

            $script_found = false;
            // check to see if we loaded the script already
            if( !in_array($script,$this->loaded_scripts) OR !in_array($script,$this->loaded_styles) ){
              foreach( $assets['scripts'] as $key=>$asset ){
                if( $script === $asset['name'] ){
                  if( $asset['host'] === "local" ){
                    $source = plugin_dir_url( __FILE__ ).'assets/js/'.$asset['uri'];
                  }else{
                    $source = $asset['uri'];
                  }

                  $version = $asset['version'];
                  $handle = $script;

                  // check dependencies
                  if( !isset($deps) )
                    $deps = array();

                  //populate script loader with loading details
                  $script_loader = array(
                    'handle'=>$handle,
                    'source'=>$source,
                    'deps'=>$deps,
                    'version'=>$version,
                    );

                  $this->front_enqueue_asset('scripts',$script_loader);

                  if( isset($asset['styles']) ){
                    foreach($asset['styles'] as $script_style ){
                      $script_style_loader = array(
                        'handle'=>$handle,
                        'deps'=>array(),
                        'version'=>$version,
                        'source'=>$script_style['uri']
                        );
                      $this->front_enqueue_asset('styles',$script_style_loader);
                    }
                  }
                  
                  $scripts_found = true;
                  break;
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
                      if( !isset($deps) )
                        $deps = array();
                      
                      //populate style loader with loading details
                      $style_loader = array(
                        'handle'=>$handle,
                        'source'=>$source,
                        'deps'=>$deps, 
                        'version'=>$version,
                      );
                      $this->front_enqueue_asset('styles',$style_loader);
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
    }
  }
   
  // Store a reference to the plugin in GLOBALS so that our unit tests can access it
  $GLOBALS['wp-enqueuer'] = new WP_Enqueuer();
     
}
